<?php

namespace App\Services\PlatformApi;

use App\Models\ApiOperationLog;
use App\Models\Store;
use RuntimeException;

class OrdersApiService
{
    public function __construct(private readonly PlatformApiAdapterFactory $factory)
    {
    }

    public function list(Store $store, array $filters, int $page, int $perPage): array
    {
        $adapter = $this->factory->make($store);
        $result = $adapter->listOrders($filters, $page, $perPage);

        ApiOperationLog::create([
            'store_id' => $store->id,
            'operation_type' => 'list',
            'resource_type' => 'order',
            'resource_id' => null,
            'payload' => $filters,
            'response' => ['total' => $result['total']],
            'status' => 'ok',
        ]);

        return $result;
    }

    public function get(Store $store, string $orderId): array
    {
        $adapter = $this->factory->make($store);

        return $adapter->getOrder($orderId);
    }

    public function update(Store $store, string $orderId, array $attributes): array
    {
        $adapter = $this->factory->make($store);

        try {
            $result = $adapter->updateOrder($orderId, $attributes);

            ApiOperationLog::create([
                'store_id' => $store->id,
                'operation_type' => 'update',
                'resource_type' => 'order',
                'resource_id' => $orderId,
                'payload' => $attributes,
                'response' => $result['data'],
                'status' => 'ok',
            ]);

            return $result;
        } catch (RuntimeException $e) {
            ApiOperationLog::create([
                'store_id' => $store->id,
                'operation_type' => 'update',
                'resource_type' => 'order',
                'resource_id' => $orderId,
                'payload' => $attributes,
                'response' => null,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
