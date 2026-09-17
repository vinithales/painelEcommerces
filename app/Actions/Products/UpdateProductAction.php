<?php

namespace App\Actions\Products;

use App\Models\AuditEvent;
use App\Models\ProductSnapshot;
use App\Models\Store;
use App\Services\PlatformApi\ProductFields;
use App\Services\PlatformApi\ProductsApiService;
use Illuminate\Support\Facades\Cache;

class UpdateProductAction
{
    public function __construct(private readonly ProductsApiService $service) {}

    public function execute(Store $store, string $productId, array $attributes, bool $dryRun = false, ?int $actor = null): array
    {
        $attributes = ProductFields::validate($store, $attributes);
        if ($dryRun) {
            return $this->service->update($store, $productId, $attributes, true);
        }

        return Cache::lock('product:'.$store->id.':'.hash('sha256', $productId), 120)->block(5, function () use ($store, $productId, $attributes, $actor) {
            $current = ProductFields::values($store, $this->service->get($store, $productId)['data']);
            $changes = array_filter($attributes, fn ($v, $k) => ($current[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH);
            if (! $changes) {
                return ['data' => $current, 'raw' => $current['raw'] ?? []];
            }
            $before = array_intersect_key($current, $changes);
            $snapshot = ProductSnapshot::create(['store_id' => $store->id, 'resource_id' => $productId, 'platform' => $store->platform, 'data_before' => $before, 'data_after' => null]);
            $result = $this->service->update($store, $productId, $changes);
            $after = $this->service->get($store, $productId);
            $snapshot->update(['data_after' => array_intersect_key(ProductFields::values($store, $after['data']), $changes)]);
            AuditEvent::record($store->id, 'product', 'update', ['id' => $productId, 'fields' => array_keys($changes)], actor: $actor);

            return $after;
        });
    }
}
