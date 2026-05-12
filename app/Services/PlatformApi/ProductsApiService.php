<?php

namespace App\Services\PlatformApi;

use App\Models\ApiOperationLog;
use App\Models\Store;
use RuntimeException;

class ProductsApiService
{
    public function __construct(
        private readonly PlatformApiAdapterFactory $factory,
        private readonly ProductsCacheService $cache,
    ) {
    }

    public function getAllCached(Store $store): array
    {
        return $this->cache->get($store);
    }

    public function startCacheWarm(Store $store): void
    {
        $this->cache->startWarm($store);
    }

    public function getCacheProgress(Store $store): array
    {
        return $this->cache->getProgress($store);
    }

    public function isCacheWarmed(Store $store): bool
    {
        return $this->cache->isWarmed($store);
    }

    public function invalidateCache(Store $store): void
    {
        $this->cache->forget($store);
    }

    public function list(Store $store, array $filters, int $page, int $perPage): array
    {
        $adapter = $this->factory->make($store);
        $result = $adapter->listProducts($filters, $page, $perPage);

        ApiOperationLog::create([
            'store_id' => $store->id,
            'operation_type' => 'list',
            'resource_type' => 'product',
            'resource_id' => null,
            'payload' => $filters,
            'response' => ['total' => $result['total']],
            'status' => 'ok',
        ]);

        return $result;
    }

    public function get(Store $store, string $productId): array
    {
        $adapter = $this->factory->make($store);

        return $adapter->getProduct($productId);
    }

    public function update(Store $store, string $productId, array $attributes, bool $dryRun = false): array
    {
        if ($dryRun) {
            $current = $this->get($store, $productId);

            return [
                'dry_run' => true,
                'product_id' => $productId,
                'current' => $current['data'],
                'proposed_changes' => $attributes,
                'diff' => $this->diff($current['data'], $attributes),
            ];
        }

        $adapter = $this->factory->make($store);

        try {
            $result = $adapter->updateProduct($productId, $attributes);

            ApiOperationLog::create([
                'store_id' => $store->id,
                'operation_type' => 'update',
                'resource_type' => 'product',
                'resource_id' => $productId,
                'payload' => $attributes,
                'response' => $result['data'],
                'status' => 'ok',
            ]);

            return $result;
        } catch (RuntimeException $e) {
            ApiOperationLog::create([
                'store_id' => $store->id,
                'operation_type' => 'update',
                'resource_type' => 'product',
                'resource_id' => $productId,
                'payload' => $attributes,
                'response' => null,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function diff(array $current, array $proposed): array
    {
        $diff = [];
        foreach ($proposed as $key => $newValue) {
            $diff[$key] = [
                'from' => $current[$key] ?? null,
                'to' => $newValue,
            ];
        }

        return $diff;
    }
}
