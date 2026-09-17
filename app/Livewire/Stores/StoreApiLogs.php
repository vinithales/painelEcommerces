<?php

namespace App\Livewire\Stores;

use App\Models\ApiOperationLog;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('API Logs')]
class StoreApiLogs extends Component
{
    use WithPagination;

    public Store $store;

    public string $operationType = 'all';

    public string $resourceType = 'all';

    public string $status = 'all';

    public string $dateFrom = '';

    public string $dateTo = '';

    public array $expandedIds = [];

    public function mount(Store $store): void
    {
        $this->store = $store;
    }

    public function updatingOperationType(): void
    {
        $this->resetPage();
    }

    public function updatingResourceType(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function toggleExpand(int $logId): void
    {
        if (in_array($logId, $this->expandedIds, true)) {
            $this->expandedIds = array_values(array_filter($this->expandedIds, fn ($id) => $id !== $logId));
        } else {
            $this->expandedIds[] = $logId;
        }
    }

    public function render(): View
    {
        $logs = ApiOperationLog::where('store_id', $this->store->id)
            ->when($this->operationType !== 'all', fn ($q) => $q->where('operation_type', $this->operationType))
            ->when($this->resourceType !== 'all', fn ($q) => $q->where('resource_type', $this->resourceType))
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest()
            ->paginate(20);

        return view('livewire.stores.store-api-logs', ['logs' => $logs])->layout('layouts.app', ['store' => $this->store]);
    }
}
