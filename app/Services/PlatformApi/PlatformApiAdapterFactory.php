<?php

namespace App\Services\PlatformApi;

use App\Models\Store;
use App\Services\PlatformApi\Adapters\Magento2ApiAdapter;
use App\Services\PlatformApi\Adapters\WooCommerceApiAdapter;
use App\Services\PlatformApi\Contracts\PlatformApiAdapter;
use InvalidArgumentException;

class PlatformApiAdapterFactory
{
    public function make(Store $store): PlatformApiAdapter
    {
        $credentials = $store->integrationCredentials();

        return match ($store->platform) {
            Store::PLATFORM_WOOCOMMERCE => new WooCommerceApiAdapter(
                storeUrl: $store->store_url,
                consumerKey: $credentials['consumer_key'] ?? '',
                consumerSecret: $credentials['consumer_secret'] ?? '',
            ),
            Store::PLATFORM_MAGENTO2 => new Magento2ApiAdapter(
                storeUrl: $store->store_url,
                consumerKey: $credentials['consumer_key'] ?? '',
                consumerSecret: $credentials['consumer_secret'] ?? '',
                accessToken: $credentials['access_token'] ?? '',
                accessTokenSecret: $credentials['access_token_secret'] ?? '',
                username: $credentials['username'] ?? null,
                password: $credentials['password'] ?? null,
            ),
            default => throw new InvalidArgumentException("Unsupported store platform [{$store->platform}]."),
        };
    }
}
