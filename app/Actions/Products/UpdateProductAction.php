<?php

namespace App\Actions\Products;

use App\Models\ProductSnapshot;
use App\Models\Store;
use App\Services\PlatformApi\ProductsApiService;

class UpdateProductAction
{
    public function __construct(private readonly ProductsApiService $service)
    {
    }

    public function execute(Store $store, string $productId, array $attributes, bool $dryRun = false): array
    {
        if ($dryRun) {
            return $this->service->update($store, $productId, $attributes, dryRun: true);
        }

        $current = $this->service->get($store, $productId);

        $snapshot = ProductSnapshot::create([
            'store_id' => $store->id,
            'resource_id' => $productId,
            'platform' => $store->platform,
            'data_before' => $current['data'],
            'data_after' => null,
        ]);

        $result = $this->service->update($store, $productId, $attributes);

        $snapshot->update(['data_after' => $result['data']]);

        return $result;
    }
}
