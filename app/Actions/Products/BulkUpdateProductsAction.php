<?php

namespace App\Actions\Products;

use App\Jobs\UpdateProductJob;
use App\Models\Store;
use App\Services\PlatformApi\ProductsApiService;

class BulkUpdateProductsAction
{
    public function __construct(private readonly ProductsApiService $service)
    {
    }

    public function execute(Store $store, array $productIds, array $attributes, bool $dryRun = false): array
    {
        if ($dryRun) {
            $previews = [];
            foreach ($productIds as $productId) {
                $previews[] = $this->service->update($store, (string) $productId, $attributes, dryRun: true);
            }

            return $previews;
        }

        foreach ($productIds as $productId) {
            UpdateProductJob::dispatch($store, (string) $productId, $attributes);
        }

        return ['dispatched' => count($productIds)];
    }
}
