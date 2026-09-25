<?php

namespace App\Services\Checks\Executors;

use App\Services\Checks\PublicHttpClient;

class HttpExecutor
{
    public function __construct(
        private readonly PublicHttpClient $http
    ){}

    public function execute(string $baseUrl, array $configuration): array
    {
        $url =  $this->http->resolve($baseUrl, $configuration['path'] ?? '/');
        $response = $this->http->fetch($url, $configuration['method'], $configuration['timeout'], $configuration['follow_redirects']);

        return [
            'status_code' => $response['status'],
            'response_time' => $response['duration_ms'],
            'url' => $response['url'],
            'body' => $response['body'],
        ];
    }
}