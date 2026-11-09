<?php

namespace App\Actions\Orders;

use App\Jobs\UpdateOrderJob;
use App\Models\Store;
use Illuminate\Support\Facades\Bus;

class BulkUpdateOrdersAction
{
    public function execute(Store $store, array $orderIds, array $attributes): array
    {
        validator(['ids' => $orderIds], ['ids' => 'required|array|max:100', 'ids.*' => 'required|string|max:255'])->validate();
        $field = $store->isMagento2() ? 'operation' : 'status';
        $allowed = $store->isMagento2() ? ['cancel', 'hold', 'unhold'] : ['pending', 'on-hold', 'processing', 'completed', 'cancelled'];
        if (array_keys($attributes) !== [$field] || ! in_array($attributes[$field], $allowed, true)) {
            throw new \RuntimeException('Operação de pedido inválida.');
        }
        $batch = Bus::batch(array_map(fn ($id) => new UpdateOrderJob($store, (string) $id, $attributes), $orderIds))
            ->name('Edição de pedidos')->allowFailures()
            ->withOption('store_id', $store->id)->withOption('resource', 'order')
            ->withOption('user_id', auth()->id())->dispatch();

        return ['dispatched' => count($orderIds), 'batch_id' => $batch->id];
    }
}
