<?php

namespace App\Services\IntegrationCheckers\Checks;

use App\Models\Store;
use Illuminate\Support\Facades\Http;

class RobotsTxtCheck implements StoreCheck
{
    public function key(): string
    {
        return 'robots_txt';
    }

    public function supports(Store $store): bool
    {
        return filled($store->store_url);
    }

    public function check(Store $store): array
    {
        $url = rtrim($store->store_url, '/') . '/robots.txt';

        $response = Http::timeout(10)->get($url);

        if ($response->successful()) {
            return [
                'status' => 'ok',
                'error' => null,
            ];
        }

        if ($response->status() === 404) {
            return [
                'status' => 'missing',
                'error' => 'robots.txt not found',
            ];
        }

        return [
            'status' => 'failed',
            'error' => 'HTTP ' . $response->status(),
        ];
    }
}