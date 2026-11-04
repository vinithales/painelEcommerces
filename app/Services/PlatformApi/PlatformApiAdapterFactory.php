<?php

namespace App\Services\PlatformApi;

use App\Models\Store;
use App\Services\PlatformApi\Adapters\Magento2ApiAdapter;
use App\Services\PlatformApi\Adapters\WooCommerceApiAdapter;
use App\Services\PlatformApi\Contracts\PlatformApiAdapter;
use InvalidArgumentException;

class PlatformApiAdapterFactory
{
    private array $adapters = [];

    public function make(Store $store): PlatformApiAdapter
    {
        $credentials = $store->integrationCredentials();
        if ($store->isWooCommerce() && (empty($credentials['consumer_key']) || empty($credentials['consumer_secret']))) {
            throw new \RuntimeException('Credenciais WooCommerce não configuradas.');
        }
        if ($store->isMagento2() && ! (isset($credentials['username'], $credentials['password']) || isset($credentials['consumer_key'], $credentials['consumer_secret'], $credentials['access_token'], $credentials['access_token_secret']))) {
            throw new \RuntimeException('Credenciais Magento não configuradas.');
        }

        $key = hash('sha256', $store->platform.'|'.$store->store_url.'|'.serialize($credentials));

        return $this->adapters[$key] ??= match ($store->platform) {
            Store::PLATFORM_WOOCOMMERCE => new WooCommerceApiAdapter(
                storeUrl: $store->store_url,
                consumerKey: $credentials['consumer_key'] ?? '',
                consumerSecret: $credentials['consumer_secret'] ?? '',
                wpUsername: $credentials['wp_username'] ?? null,
                wpApplicationPassword: $credentials['wp_application_password'] ?? null,
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
