<?php

namespace App\Livewire\Stores;

use App\Actions\Orders\BulkUpdateOrdersAction;
use App\Actions\Orders\UpdateOrderAction;
use App\Models\Store;
use App\Services\PlatformApi\OrdersApiService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('Orders')]
class StoreOrders extends Component
{
    public Store $store;

    public array $filters = [
        'status' => '',
        'date_from' => '',
        'date_to' => '',
        'customer_email' => '',
    ];

    public int $page = 1;

    public int $perPage = 50;

    public int $total = 0;

    public array $pendingChanges = [];

    public array $executionFeedback = [];

    public bool $showBulkModal = false;

    public array $bulkSelectedIds = [];

    public array $bulkAttributes = [];

    public function mount(Store $store): void
    {
        $this->store = $store->load(['wooCommerceIntegration', 'magento2Integration']);
    }

    public function getGridRows(OrdersApiService $service): array
    {
        try {
            $result = $service->list($this->store, $this->filters, $this->page, $this->perPage);
            $this->total = $result['total'];

            return $result['data'];
        } catch (Throwable $e) {
            $this->addError('api', $e->getMessage());

            return [];
        }
    }

    public function queueChange(string $orderId, string $field, mixed $value): void
    {
        if (! isset($this->pendingChanges[$orderId])) {
            $this->pendingChanges[$orderId] = [];
        }
        $this->pendingChanges[$orderId][$field] = $value;
    }

    public function applyChanges(UpdateOrderAction $action): void
    {
        $this->executionFeedback = [];

        foreach ($this->pendingChanges as $orderId => $attributes) {
            try {
                $action->execute($this->store, $orderId, $attributes);
                $this->executionFeedback[] = [
                    'id' => $orderId,
                    'status' => 'ok',
                    'message' => 'Updated successfully',
                ];
            } catch (Throwable $e) {
                $this->executionFeedback[] = [
                    'id' => $orderId,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
            }
        }

        $this->pendingChanges = [];
        $this->dispatch('orders-grid-refresh', rows: $this->getGridRows(app(OrdersApiService::class)));
    }

    public function openBulkApplyModal(array $ids): void
    {
        $this->bulkSelectedIds = $ids;
        $this->bulkAttributes = ['status' => ''];
        $this->showBulkModal = true;
    }

    public function applyBulk(BulkUpdateOrdersAction $action): void
    {
        if (empty($this->bulkSelectedIds) || empty($this->bulkAttributes)) {
            return;
        }

        $result = $action->execute($this->store, $this->bulkSelectedIds, $this->bulkAttributes);
        $this->showBulkModal = false;
        $this->bulkSelectedIds = [];

        $dispatched = $result['dispatched'] ?? 0;
        session()->flash('status', "Bulk update queued for {$dispatched} order(s).");
        $this->dispatch('orders-grid-refresh', rows: $this->getGridRows(app(OrdersApiService::class)));
    }

    public function loadOrders(OrdersApiService $service): void
    {
        $rows = $this->getGridRows($service);
        $this->dispatch('orders-loaded', rows: $rows);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.stores.store-orders');
    }
}
