<?php

namespace App\Jobs;

use App\Models\ApiOperationLog;
use App\Models\Store;
use App\Services\PlatformApi\PlatformApiAdapterFactory;
use App\Services\PlatformApi\ProductsCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class WarmProductsCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public readonly Store $store)
    {
    }

    public function handle(PlatformApiAdapterFactory $factory, ProductsCacheService $cache): void
    {
        $store = $this->store->load(['wooCommerceIntegration', 'magento2Integration']);
        $adapter = $factory->make($store);

        try {
            $cache->warm($store, $adapter);

            ApiOperationLog::create([
                'store_id' => $store->id,
                'operation_type' => 'cache_warm',
                'resource_type' => 'product',
                'resource_id' => null,
                'payload' => [],
                'response' => ['cached' => count($cache->get($store))],
                'status' => 'ok',
            ]);
        } catch (Throwable $e) {
            $cache->markFailed($store, $e->getMessage());

            throw $e;
        }
    }
}
