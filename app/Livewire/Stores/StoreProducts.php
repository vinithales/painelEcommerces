<?php

namespace App\Livewire\Stores;

use App\Actions\Products\BulkUpdateProductsAction;
use App\Actions\Products\RollbackProductAction;
use App\Actions\Products\UpdateProductAction;
use App\Models\BulkEditTemplate;
use App\Models\ProductSnapshot;
use App\Models\Store;
use App\Services\PlatformApi\ProductsApiService;
use App\Services\PlatformApi\ProductsCacheService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;
use Throwable;

#[Layout('layouts.app')]
#[Title('Products')]
class StoreProducts extends Component
{
    public Store $store;

    /** idle | warming | ready | failed */
    public string $cacheStatus = 'idle';

    public int $cacheProgressFetched = 0;

    public int $cacheProgressTotal = 0;

    public array $pendingChanges = [];

    public bool $dryRunMode = false;

    public array $dryRunPreviews = [];

    public array $executionFeedback = [];

    public bool $showBulkModal = false;

    public array $bulkSelectedIds = [];

    public array $bulkAttributes = [];

    public ?int $selectedTemplateId = null;

    public function mount(Store $store, ProductsApiService $service): void
    {
        $this->store = $store->load(['wooCommerceIntegration', 'magento2Integration']);

        if ($service->isCacheWarmed($this->store)) {
            $this->cacheStatus = 'ready';
            $rows = $service->getAllCached($this->store);
            $this->dispatch('products-ready', rows: $rows);
        } else {
            $this->syncCacheStatus($service);
            if ($this->cacheStatus === 'idle') {
                $service->startCacheWarm($this->store);
                $this->cacheStatus = 'warming';
            }
        }
    }

    public function checkCacheProgress(ProductsApiService $service): void
    {
        if ($service->isCacheWarmed($this->store)) {
            $this->cacheStatus = 'ready';
            $rows = $service->getAllCached($this->store);
            $this->dispatch('products-ready', rows: $rows);
            return;
        }

        $this->syncCacheStatus($service);
    }

    public function refreshCache(ProductsApiService $service): void
    {
        $service->invalidateCache($this->store);
        $service->startCacheWarm($this->store);
        $this->cacheStatus = 'warming';
        $this->cacheProgressFetched = 0;
        $this->cacheProgressTotal = 0;
    }

    public function queueChange(string $productId, string $field, mixed $value): void
    {
        if (! isset($this->pendingChanges[$productId])) {
            $this->pendingChanges[$productId] = [];
        }
        $this->pendingChanges[$productId][$field] = $value;
    }

    public function applyChanges(UpdateProductAction $action, ProductsApiService $service): void
    {
        $this->executionFeedback = [];

        foreach ($this->pendingChanges as $productId => $attributes) {
            try {
                $action->execute($this->store, $productId, $attributes, $this->dryRunMode);
                $this->executionFeedback[] = [
                    'id' => $productId,
                    'status' => 'ok',
                    'message' => $this->dryRunMode ? 'Preview OK' : 'Updated successfully',
                ];
            } catch (Throwable $e) {
                $this->executionFeedback[] = [
                    'id' => $productId,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
            }
        }

        if (! $this->dryRunMode) {
            $this->pendingChanges = [];
            $service->invalidateCache($this->store);
            $service->startCacheWarm($this->store);
            $this->cacheStatus = 'warming';
        }
    }

    public function previewChanges(UpdateProductAction $action): void
    {
        $this->dryRunPreviews = [];

        foreach ($this->pendingChanges as $productId => $attributes) {
            try {
                $preview = $action->execute($this->store, $productId, $attributes, dryRun: true);
                $this->dryRunPreviews[] = $preview;
            } catch (Throwable $e) {
                $this->dryRunPreviews[] = [
                    'product_id' => $productId,
                    'error' => $e->getMessage(),
                ];
            }
        }
    }

    public function openBulkApplyModal(array $ids): void
    {
        $this->bulkSelectedIds = $ids;
        $this->bulkAttributes = [];
        $this->selectedTemplateId = null;
        $this->showBulkModal = true;
    }

    public function loadTemplate(): void
    {
        if (! $this->selectedTemplateId) {
            return;
        }

        $template = BulkEditTemplate::find($this->selectedTemplateId);
        if ($template) {
            $this->bulkAttributes = $template->attribute_changes;
        }
    }

    public function applyBulk(BulkUpdateProductsAction $action, ProductsApiService $service): void
    {
        if (empty($this->bulkSelectedIds) || empty($this->bulkAttributes)) {
            return;
        }

        $result = $action->execute($this->store, $this->bulkSelectedIds, $this->bulkAttributes, $this->dryRunMode);

        $this->showBulkModal = false;
        $this->bulkSelectedIds = [];

        if ($this->dryRunMode) {
            $this->dryRunPreviews = $result;
        } else {
            $dispatched = $result['dispatched'] ?? 0;
            session()->flash('status', "Bulk update queued for {$dispatched} product(s).");
            $service->invalidateCache($this->store);
            $service->startCacheWarm($this->store);
            $this->cacheStatus = 'warming';
        }
    }

    public function rollback(int $snapshotId, RollbackProductAction $action, ProductsApiService $service): void
    {
        $snapshot = ProductSnapshot::find($snapshotId);

        if (! $snapshot || $snapshot->store_id !== $this->store->id) {
            return;
        }

        try {
            $action->execute($snapshot);
            session()->flash('status', "Product #{$snapshot->resource_id} rolled back successfully.");
            $service->invalidateCache($this->store);
            $service->startCacheWarm($this->store);
            $this->cacheStatus = 'warming';
        } catch (RuntimeException $e) {
            $this->addError('rollback', $e->getMessage());
        }
    }

    public function getTemplatesProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return BulkEditTemplate::where('resource_type', 'product')
            ->where(fn ($q) => $q->whereNull('store_id')->orWhere('store_id', $this->store->id))
            ->orderBy('name')
            ->get();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $snapshots = ProductSnapshot::where('store_id', $this->store->id)
            ->whereNull('reverted_at')
            ->latest()
            ->limit(20)
            ->get();

        return view('livewire.stores.store-products', [
            'snapshots' => $snapshots,
            'templates' => $this->getTemplatesProperty(),
        ]);
    }

    private function syncCacheStatus(ProductsApiService $service): void
    {
        $progress = $service->getCacheProgress($this->store);
        $this->cacheStatus = $progress['status'];
        $this->cacheProgressFetched = $progress['fetched'] ?? 0;
        $this->cacheProgressTotal = $progress['total'] ?? 0;
    }
}

