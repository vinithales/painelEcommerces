<?php

namespace App\Actions\Orders;

use App\Jobs\UpdateOrderJob;
use App\Models\Store;

class BulkUpdateOrdersAction
{
    public function execute(Store $store, array $orderIds, array $attributes): array
    {
        validator(['ids' => $orderIds], ['ids' => 'required|array|max:100', 'ids.*' => 'required|string|max:255'])->validate();
        $field = $store->isMagento2() ? 'operation' : 'status';
        $allowed = $store->isMagento2() ? ['cancel', 'hold', 'unhold'] : ['pending', 'on-hold', 'processing', 'completed', 'cancelled'];
        if (array_keys($attributes) !== [$field] || !in_array($attributes[$field], $allowed, true)) throw new \RuntimeException('Operação de pedido inválida.');
        foreach ($orderIds as $orderId) {
            UpdateOrderJob::dispatch($store, (string) $orderId, $attributes);
        }

        return ['dispatched' => count($orderIds)];
    }
}
