<?php

namespace App\Actions\Orders;

use App\Jobs\UpdateOrderJob;
use App\Models\Store;

class BulkUpdateOrdersAction
{
    public function execute(Store $store, array $orderIds, array $attributes): array
    {
        foreach ($orderIds as $orderId) {
            UpdateOrderJob::dispatch($store, (string) $orderId, $attributes);
        }

        return ['dispatched' => count($orderIds)];
    }
}
