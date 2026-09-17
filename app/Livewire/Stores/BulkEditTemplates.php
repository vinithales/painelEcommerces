<?php

namespace App\Livewire\Stores;

use App\Models\BulkEditTemplate;
use App\Models\Store;
use App\Services\PlatformApi\ProductFields;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Bulk Edit Templates')]
class BulkEditTemplates extends Component
{
    public Store $store;

    public string $name = '';

    public string $resourceType = 'product';

    public string $rawAttributes = '';

    public bool $isGlobal = false;

    public ?int $editingId = null;

    public bool $showForm = false;

    public function mount(Store $store): void
    {
        $this->store = $store;
    }

    public function openCreate(): void
    {
        $this->reset(['name', 'resourceType', 'rawAttributes', 'isGlobal', 'editingId']);
        $this->resourceType = 'product';
        $this->showForm = true;
    }

    public function openEdit(int $templateId): void
    {
        Gate::authorize('manage');
        $template = BulkEditTemplate::where(fn ($q) => $q->whereNull('store_id')->orWhere('store_id', $this->store->id))->findOrFail($templateId);
        if (! $template) {
            return;
        }

        $this->editingId = $templateId;
        $this->name = $template->name;
        $this->resourceType = $template->resource_type;
        $this->rawAttributes = json_encode($template->attribute_changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->isGlobal = $template->store_id === null;
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('manage');
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'resourceType' => ['required', 'in:product,order'],
            'rawAttributes' => ['required', 'json'],
        ]);

        $attributeChanges = json_decode($this->rawAttributes, true);
        if ($this->resourceType === 'product') {
            ProductFields::validate($this->store, $attributeChanges);
        } else {
            validator(['attributes' => $attributeChanges], ['attributes' => 'required|array', 'attributes.status' => 'sometimes|in:pending,processing,on-hold,completed,cancelled', 'attributes.operation' => 'sometimes|in:cancel,hold,unhold'])->validate();
            abort_unless(count($attributeChanges) === 1 && count(array_intersect(array_keys($attributeChanges), ['status', 'operation'])) === 1, 422);
        }

        $data = [
            'name' => $this->name,
            'resource_type' => $this->resourceType,
            'attribute_changes' => $attributeChanges,
            'store_id' => $this->isGlobal ? null : $this->store->id,
        ];

        if ($this->editingId) {
            BulkEditTemplate::where(fn ($q) => $q->whereNull('store_id')->orWhere('store_id', $this->store->id))->findOrFail($this->editingId)->update($data);
        } else {
            BulkEditTemplate::create($data);
        }

        $this->showForm = false;
        $this->reset(['name', 'resourceType', 'rawAttributes', 'isGlobal', 'editingId']);
        session()->flash('status', 'Template saved.');
    }

    public function delete(int $templateId): void
    {
        Gate::authorize('manage');
        BulkEditTemplate::where(fn ($q) => $q->whereNull('store_id')->orWhere('store_id', $this->store->id))->findOrFail($templateId)->delete();
        session()->flash('status', 'Template deleted.');
    }

    public function render(): View
    {
        $templates = BulkEditTemplate::where(
            fn ($q) => $q->whereNull('store_id')->orWhere('store_id', $this->store->id)
        )
            ->orderBy('resource_type')
            ->orderBy('name')
            ->get();

        return view('livewire.stores.bulk-edit-templates', ['templates' => $templates]);
    }
}
