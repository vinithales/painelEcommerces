<?php

namespace App\Services\PlatformApi;

use App\Models\Store;
use App\Services\PlatformApi\Adapters\Magento2ApiAdapter;
use Illuminate\Validation\ValidationException;

class ProductsApiService
{
    public function __construct(private readonly PlatformApiAdapterFactory $factory, private readonly ProductsCacheService $cache, private readonly ProductImageFetcher $imageFetcher) {}

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

    public function productAttributeSets(Store $store): array
    {
        $adapter = $this->factory->make($store);

        return $adapter instanceof Magento2ApiAdapter ? $adapter->productAttributeSets() : [];
    }

    public function create(Store $store, array $attributes, array $images = []): array
    {
        if ($store->isMagento2()) {
            foreach ($images as $index => $image) {
                if (isset($image['src'])) {
                    $images[$index] = $this->imageFetcher->fetch($image['src'], $image['alt'] ?? '', $image['name'] ?? '')
                        + array_intersect_key($image, array_flip(['label', 'position', 'disabled', 'role']));
                }
            }
        }
        [$attributes, $images] = ProductCreationFields::validate($store, $attributes, $images);
        if ($store->isMagento2() && ! in_array((int) $attributes['attribute_set_id'], array_column($this->productAttributeSets($store), 'attribute_set_id'), false)) {
            throw ValidationException::withMessages(['form.attribute_set_id' => 'Selecione um conjunto de atributos de produto válido para esta loja.']);
        }
        $result = ApiAudit::call(
            $store,
            'create',
            'product',
            null,
            $attributes + ['image_count' => count($images)],
            fn () => $this->factory->make($store)->createProduct($attributes, $images),
        );
        $this->invalidateCache($store);

        return $result;
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
