<?php

namespace App\Services\PlatformApi;

use App\Jobs\WarmProductsCacheJob;
use App\Models\Store;
use App\Services\PlatformApi\Contracts\PlatformApiAdapter;
use Illuminate\Support\Facades\Cache;

class ProductsCacheService
{
    public const TTL_MINUTES = 60;

    public const PAGE_SIZE = 200;

    public function cacheKey(Store $store): string
    {
        return "products_cache_{$store->id}";
    }

    public function progressKey(Store $store): string
    {
        return "products_cache_progress_{$store->id}";
    }

    public function isWarmed(Store $store): bool
    {
        return Cache::has($this->cacheKey($store));
    }

    public function getProgress(Store $store): array
    {
        return Cache::get($this->progressKey($store), [
            'status' => 'idle',
            'fetched' => 0,
            'total' => 0,
        ]);
    }

    public function startWarm(Store $store): void
    {
        Cache::put($this->progressKey($store), [
            'status' => 'warming',
            'fetched' => 0,
            'total' => 0,
        ], now()->addMinutes(self::TTL_MINUTES + 10));

        WarmProductsCacheJob::dispatch($store);
    }

    public function warm(Store $store, PlatformApiAdapter $adapter): void
    {
        $all = [];
        $page = 1;

        do {
            $result = $adapter->listProducts([], $page, self::PAGE_SIZE);
            $items = $result['data'] ?? [];
            $total = $result['total'] ?? 0;

            foreach ($items as &$item) {
                unset($item['raw']);
            }
            unset($item);

            $all = array_merge($all, $items);
            $fetched = count($all);

            Cache::put($this->progressKey($store), [
                'status' => 'warming',
                'fetched' => $fetched,
                'total' => $total,
            ], now()->addMinutes(self::TTL_MINUTES + 10));

            $page++;
        } while ($fetched < $total && count($items) > 0);

        Cache::put($this->cacheKey($store), $all, now()->addMinutes(self::TTL_MINUTES));
        Cache::put($this->progressKey($store), [
            'status' => 'ready',
            'fetched' => count($all),
            'total' => count($all),
        ], now()->addMinutes(self::TTL_MINUTES));
    }

    public function markFailed(Store $store, string $error): void
    {
        Cache::put($this->progressKey($store), [
            'status' => 'failed',
            'fetched' => 0,
            'total' => 0,
            'error' => $error,
        ], now()->addMinutes(10));
    }

    public function forget(Store $store): void
    {
        Cache::forget($this->cacheKey($store));
        Cache::forget($this->progressKey($store));
    }

    public function get(Store $store): array
    {
        return Cache::get($this->cacheKey($store), []);
    }
}
