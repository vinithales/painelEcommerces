<?php

namespace App\Livewire\Stores;

use App\Actions\Orders\BulkUpdateOrdersAction;
use App\Actions\Products\BulkUpdateProductsAction;
use App\Actions\Products\RollbackProductAction;
use App\Actions\Products\UpdateProductAction;
use App\Models\ApiOperationLog;
use App\Models\BulkEditTemplate;
use App\Models\ProductSnapshot;
use App\Models\Store;
use App\Services\PlatformApi\OrdersApiService;
use App\Services\PlatformApi\ProductFields;
use App\Services\PlatformApi\ProductsApiService;
use App\Support\Redactor;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

abstract class ResourceTable extends Component
{
    public Store $store;

    #[Url]
    public array $filters = ['name' => '', 'sku' => '', 'sku_prefix' => '', 'status' => '', 'date_from' => '', 'date_to' => '', 'customer_email' => '', 'number' => '', 'sort' => '', 'direction' => 'asc'];

    #[Url]
    public int $page = 1;

    #[Url]
    public int $perPage = 50;

    public int $total = 0;

    #[Locked]
    public ?string $selectedKey = null;

    #[Locked]
    public array $detail = [];

    public array $draft = [];

    public array $previews = [];

    public array $pendingChanges = [];

    public array $feedback = [];

    public bool $showBulkModal = false;

    public array $bulkSelectedIds = [];

    public string $bulkField = 'status';

    public string $bulkValue = '';

    public ?int $templateId = null;

    abstract protected function resource(): string;

    protected function service(): ProductsApiService|OrdersApiService
    {
        return app($this->resource() === 'product' ? ProductsApiService::class : OrdersApiService::class);
    }

    public function mount(Store $store): void
    {
        $this->store = $store;
    }

    public function boot(): void
    {
        Gate::authorize('operate');
    }

    public function loadRows(): array
    {
        $this->resetErrorBag();
        $this->validate(['page' => 'integer|min:1|max:100000', 'perPage' => 'integer|in:25,50,100', 'filters' => 'array', 'filters.*' => 'nullable|string|max:255', 'filters.direction' => 'in:asc,desc', 'filters.sort' => 'nullable|in:id,name,price,created_at', 'filters.date_from' => 'nullable|date', 'filters.date_to' => 'nullable|date']);
        try {
            $r = $this->service()->list($this->store, array_filter($this->filters, fn ($v) => $v !== ''), $this->page, $this->perPage);
            $this->total = $r['total'];

            return array_map(fn ($row) => Redactor::clean(array_diff_key($row, array_flip(['raw', 'description', 'short_description', 'gallery', 'attributes', 'variations', 'categories', 'items', 'shipping_address']))), $r['data']);
        } catch (\Throwable $e) {
            $this->total = 0;
            $this->addError('api', Redactor::clean($e->getMessage()));

            return [];
        }
    }

    public function refreshRows(): void
    {
        $this->dispatch('resource-rows', rows: $this->loadRows());
    }

    public function applyFilters(): void
    {
        $this->page = 1;
        $this->refreshRows();
    }

    public function movePage(int $direction): void
    {
        $this->page = max(1, min((int) max(1, ceil($this->total / $this->perPage)), $this->page + ($direction > 0 ? 1 : -1)));
        $this->refreshRows();
    }

    public function openDetail(string $key): void
    {
        $this->resetErrorBag();
        $this->previews = [];
        try {
            $this->detail = Redactor::clean($this->service()->get($this->store, $key)['data']);
            $this->selectedKey = $key;
            $this->draft = $this->resource() === 'product' ? array_intersect_key(ProductFields::values($this->store, $this->detail), ProductFields::rules($this->store)) : [($this->store->isMagento2() ? 'operation' : 'status') => ($this->store->isMagento2() ? '' : $this->detail['status'])];
            $this->dispatch('edits-saved');
        } catch (\Throwable $e) {
            $this->addError('api', Redactor::clean($e->getMessage()));
        }
    }

    public function closeDetail(): void
    {
        $this->selectedKey = null;
        $this->detail = [];
        $this->draft = [];
        $this->previews = [];
        $this->dispatch('edits-saved');
    }

    protected function changes(): array
    {
        $baseline = $this->resource() === 'product' ? ProductFields::values($this->store, $this->detail) : $this->detail;

        return array_filter($this->draft, fn ($value, $field) => ($baseline[$field] ?? null) != $value, ARRAY_FILTER_USE_BOTH);
    }

    public function preview(): void
    {
        Gate::authorize('manage');
        if ($this->resource() !== 'product' || ! $this->selectedKey) {
            return;
        }
        try {
            $this->previews = [app(UpdateProductAction::class)->execute($this->store, $this->selectedKey, $this->changes(), true)];
        } catch (\Throwable $e) {
            $this->addError('edit', Redactor::clean($e->getMessage()));
        }
    }

    public function saveDetail(): void
    {
        Gate::authorize('manage');
        if (! $this->selectedKey) {
            return;
        }
        try {
            $changes = $this->changes();
            if (! $changes) {
                $this->dispatch('edits-saved');

                return;
            }
            if ($this->resource() === 'product') {
                app(UpdateProductAction::class)->execute($this->store, $this->selectedKey, $changes);
            } else {
                $this->service()->update($this->store, $this->selectedKey, $changes);
            }
            $this->openDetail($this->selectedKey);
            $this->refreshRows();
            session()->flash('status', 'Alteração confirmada pela plataforma.');
        } catch (\Throwable $e) {
            $this->addError('edit', Redactor::clean($e->getMessage()));
        }
    }

    public function queueChange(string $id, string $field, mixed $value): void
    {
        Gate::authorize('manage');
        if ($this->resource() === 'product') {
            ProductFields::validate($this->store, [$field => $value]);
        } else {
            abort_unless($field === ($this->store->isMagento2() ? 'operation' : 'status'), 422);
        }
        $this->pendingChanges[$id][$field] = $value;
    }

    public function applyChanges(): void
    {
        Gate::authorize('manage');
        $this->feedback = [];
        foreach ($this->pendingChanges as $id => $changes) {
            try {
                if ($this->resource() === 'product') {
                    app(UpdateProductAction::class)->execute($this->store, (string) $id, $changes);
                } else {
                    $this->service()->update($this->store, (string) $id, $changes);
                }
                unset($this->pendingChanges[$id]);
                $this->feedback[] = ['id' => $id, 'status' => 'OK'];
            } catch (\Throwable $e) {
                $this->feedback[] = ['id' => $id, 'status' => 'Falha', 'error' => Redactor::clean($e->getMessage())];
            }
        }$this->refreshRows();
    }

    public function openBulkApplyModal(array $ids): void
    {
        Gate::authorize('manage');
        validator(['ids' => $ids], ['ids' => 'required|array|max:100', 'ids.*' => 'required|string|max:255'])->validate();
        $this->bulkSelectedIds = array_values(array_unique($ids));
        $this->showBulkModal = true;
        $this->templateId = null;
        $this->bulkField = $this->resource() === 'order' && $this->store->isMagento2() ? 'operation' : 'status';
    }

    public function applyBulk(bool $preview = false): void
    {
        Gate::authorize('manage');
        $this->validate(['bulkSelectedIds' => 'required|array|max:100', 'bulkSelectedIds.*' => 'required|string|max:255', 'bulkField' => 'required|string', 'bulkValue' => 'nullable|string|max:100000']);
        $attributes = [$this->bulkField => $this->bulkValue];
        if ($this->templateId) {
            $attributes = BulkEditTemplate::where('resource_type', $this->resource())->where(fn ($q) => $q->whereNull('store_id')->orWhere('store_id', $this->store->id))->findOrFail($this->templateId)->attribute_changes;
        }
        try {
            if ($this->resource() === 'product') {
                ProductFields::validate($this->store, $attributes);
                $r = app(BulkUpdateProductsAction::class)->execute($this->store, $this->bulkSelectedIds, $attributes, $preview);
                if ($preview) {
                    $this->previews = $r;

                    return;
                }
            } else {
                abort_if($preview, 422);
                app(BulkUpdateOrdersAction::class)->execute($this->store, $this->bulkSelectedIds, $attributes);
            }
            $this->showBulkModal = false;
            session()->flash('status', 'Lote enfileirado. Consulte o histórico de API para acompanhar cada resultado.');
        } catch (\Throwable $e) {
            $this->addError('bulk', Redactor::clean($e->getMessage()));
        }
    }

    public function rollback(int $id): void
    {
        Gate::authorize('manage');
        $snapshot = ProductSnapshot::where('store_id', $this->store->id)->findOrFail($id);
        try {
            app(RollbackProductAction::class)->execute($snapshot);
            $this->refreshRows();
            if ($this->selectedKey) {
                $this->openDetail($this->selectedKey);
            }session()->flash('status', 'Rollback concluído.');
        } catch (\Throwable $e) {
            $this->addError('edit', Redactor::clean($e->getMessage()));
        }
    }

    public function render()
    {
        $resource = $this->resource();

        return view('livewire.stores.resource-table', [
            'resource' => $resource,
            'editableFields' => $resource === 'product' ? ProductFields::rules($this->store) : [],
            'templates' => BulkEditTemplate::where('resource_type', $resource)->where(fn ($q) => $q->whereNull('store_id')->orWhere('store_id', $this->store->id))->orderBy('name')->get(),
            'snapshots' => $resource === 'product' ? ProductSnapshot::where('store_id', $this->store->id)->whereNotNull('data_after')->whereNull('reverted_at')->latest()->limit(20)->get() : collect(),
            'history' => $this->selectedKey ? ApiOperationLog::where('store_id', $this->store->id)->where('resource_type', $resource)->where('resource_id', $this->selectedKey)->latest()->limit(15)->get() : collect(),
        ])->layout('layouts.app', ['store' => $this->store]);
    }
}
