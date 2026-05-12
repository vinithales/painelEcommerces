<?php

namespace App\Actions\Orders;

use App\Models\Store;
use App\Services\PlatformApi\OrdersApiService;

class UpdateOrderAction
{
    public function __construct(private readonly OrdersApiService $service)
    {
    }

    public function execute(Store $store, string $orderId, array $attributes): array
    {
        return $this->service->update($store, $orderId, $attributes);
    }
}
