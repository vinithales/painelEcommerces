<?php

namespace App\Services\Checks\Executors;

use App\Services\Checks\PublicHttpClient;
use Illuminate\Support\Facades\Http;

class BrowserExecutor
{
    public function __construct(private readonly PublicHttpClient $http) {}

    public function execute(string $baseUrl, array $configuration): array
    {
        $url = $this->http->resolve($baseUrl, $configuration['path']);
        $this->http->address($url);
        $response = Http::timeout(45)->withToken(config('checks.browser_token'))
            ->post(config('checks.browser_url').'/check', [
                'url' => $url,
                'timeout' => $configuration['timeout'],
                'wait_ms' => $configuration['wait_ms'],
            ])->throw()->json();

        if (! is_array($response) || isset($response['error'])) {
            throw new \RuntimeException($response['error'] ?? 'Resposta invalida do navegador.');
        }

        return $response;
    }
}
