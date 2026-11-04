<?php

namespace App\Services\Checks\Executors;

use App\Services\Checks\PublicHttpClient;

class HttpExecutor
{
    public function __construct(
        private readonly PublicHttpClient $http
    ) {}

    public function execute(string $baseUrl, array $configuration): array
    {
        $url = $this->http->resolve($baseUrl, $configuration['path'] ?? '/');
        $samples = [];
        for ($i = 0; $i < ($configuration['samples'] ?? 1); $i++) {
            $response = $this->http->fetch($url, $configuration['method'], $configuration['timeout'], $configuration['follow_redirects']);
            $headers = [];
            foreach ($response['headers'] ?? [] as $name => $values) {
                $headers[strtolower($name)] = implode(', ', (array) $values);
            }
            // Do not persist cookies or authentication headers in the history.
            unset($headers['set-cookie'], $headers['authorization'], $headers['proxy-authenticate']);
            $samples[] = ['status_code' => $response['status'], 'url' => $response['url'], 'headers' => $headers];
        }

        $cacheHeader = null;
        $cacheStatus = 'UNKNOWN';
        foreach (['x-cache', 'x-magento-cache-debug', 'cf-cache-status', 'x-litespeed-cache', 'x-proxy-cache', 'x-cache-status'] as $name) {
            if (! isset($headers[$name])) {
                continue;
            }
            $cacheHeader = $name;
            $value = strtoupper($headers[$name]);
            $cacheStatus = str_contains($value, 'MISS') ? 'MISS' : (str_contains($value, 'HIT') ? 'HIT' : 'UNKNOWN');
            break;
        }

        return [
            'status_code' => $response['status'],
            'response_time' => $response['duration_ms'],
            'url' => $response['url'],
            'body' => $response['body'],
            'headers' => $headers,
            'samples' => $samples,
            'cache_status' => $cacheStatus,
            'cache_header' => $cacheHeader,
        ];
    }
}
