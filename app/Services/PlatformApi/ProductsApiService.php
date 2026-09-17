<?php

namespace App\Services\PlatformApi;

use App\Models\Store;

class ProductsApiService
{
    public function __construct(private readonly PlatformApiAdapterFactory $factory, private readonly ProductsCacheService $cache) {}

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
        return ApiAudit::call($store, 'list', 'product', null, $filters, fn () => $this->factory->make($store)->listProducts($filters, max(1, $page), min(100, max(1, $perPage))));
    }

    public function get(Store $store, string $id): array
    {
        return ApiAudit::call($store, 'get', 'product', $id, [], fn () => $this->factory->make($store)->getProduct($id));
    }

    public function update(Store $store, string $id, array $attributes, bool $dryRun = false): array
    {
        $attributes = ProductFields::validate($store, $attributes);
        if ($dryRun) {
            $current = ProductFields::values($store, $this->get($store, $id)['data']);
            $diff = [];
            foreach ($attributes as $k => $v) {
                if (($current[$k] ?? null) != $v) {
                    $diff[$k] = ['from' => $current[$k] ?? null, 'to' => $v];
                }
            }

            return ['dry_run' => true, 'product_id' => $id, 'current' => $current, 'proposed_changes' => $attributes, 'diff' => $diff];
        }
        $result = ApiAudit::call($store, 'update', 'product', $id, $attributes, fn () => $this->factory->make($store)->updateProduct($id, $attributes));
        $this->invalidateCache($store);

        return $result;
    }
}
