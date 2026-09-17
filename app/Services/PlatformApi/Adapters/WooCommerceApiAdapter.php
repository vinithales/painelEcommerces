<?php

namespace App\Services\PlatformApi\Adapters;

use App\Services\PlatformApi\Contracts\PlatformApiAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class WooCommerceApiAdapter implements PlatformApiAdapter
{
    private Client $client;
    private array $lastResponse = [];

    public function telemetry(): array { return $this->lastResponse; }
    public function storeInformation(): array { return $this->request('GET', 'system_status')['body']; }

    public function __construct(
        private readonly string $storeUrl,
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
        ?Client $client = null,
    ) {
        $this->client = $client ?? new Client([
            'base_uri' => rtrim($storeUrl, '/') . '/wp-json/wc/v3/',
            'auth' => [$consumerKey, $consumerSecret],
            'timeout' => 30,
            'verify' => true,
            'allow_redirects' => false,
        ]);
    }

    public function listProducts(array $filters, int $page, int $perPage): array
    {
        $query = array_filter([
            'page' => $page,
            'per_page' => $perPage,
            'sku' => $filters['sku'] ?? null,
            'status' => $filters['status'] ?? null,
            'after' => $filters['date_from'] ?? null,
            'before' => $filters['date_to'] ?? null,
            'include' => isset($filters['ids']) ? implode(',', $filters['ids']) : null,
            'search' => $filters['name'] ?? null,
            'orderby' => ($filters['sort'] ?? '') === 'name' ? 'title' : ($filters['sort'] ?? 'id'),
            'order' => $filters['direction'] ?? 'asc',
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->request('GET', 'products', ['query' => $query]);
        $raw = $response['body'];
        $total = (int) ($response['headers']['X-WP-Total'][0] ?? count($raw));

        $items = array_map(fn (array $p) => $this->normalizeProduct($p), $raw);
        if (!empty($filters['sku_prefix'])) {
            $items = array_values(array_filter($items, fn ($p) => str_starts_with(strtolower($p['sku']), strtolower($filters['sku_prefix']))));
        }
        return [
            'data' => $items,
            'raw' => $raw,
            'total' => $total,
        ];
    }

    public function getProduct(string $id): array
    {
        $id = rawurlencode($id);
        $response = $this->request('GET', "products/{$id}");
        $raw = $response['body'];

        return [
            'data' => $this->normalizeProduct($raw),
            'raw' => $raw,
        ];
    }

    public function updateProduct(string $id, array $attributes): array
    {
        $id = rawurlencode($id);
        $attributes = \App\Services\PlatformApi\ProductFields::payload('woocommerce', $attributes);
        $response = $this->request('PUT', "products/{$id}", ['json' => $attributes]);
        $raw = $response['body'];

        return [
            'data' => $this->normalizeProduct($raw),
            'raw' => $raw,
        ];
    }

    public function listOrders(array $filters, int $page, int $perPage): array
    {
        $query = array_filter([
            'page' => $page,
            'per_page' => $perPage,
            'status' => $filters['status'] ?? null,
            'after' => $filters['date_from'] ?? null,
            'before' => $filters['date_to'] ?? null,
            'search' => $filters['customer_email'] ?? null,
            'include' => !empty($filters['number']) ? $filters['number'] : null,
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->request('GET', 'orders', ['query' => $query]);
        $raw = $response['body'];
        $total = (int) ($response['headers']['X-WP-Total'][0] ?? count($raw));

        return [
            'data' => array_map(fn (array $o) => $this->normalizeOrder($o), $raw),
            'raw' => $raw,
            'total' => $total,
        ];
    }

    public function getOrder(string $id): array
    {
        $id = rawurlencode($id);
        $response = $this->request('GET', "orders/{$id}");
        $raw = $response['body'];

        return [
            'data' => $this->normalizeOrder($raw),
            'raw' => $raw,
        ];
    }

    public function updateOrder(string $id, array $attributes): array
    {
        $current = $this->getOrder($id);
        \App\Services\PlatformApi\OrderTransitions::validateWoo($current['data'], $attributes);
        $id = rawurlencode($id);
        $response = $this->request('PUT', "orders/{$id}", ['json' => $attributes]);
        $result = $this->getOrder(rawurldecode($id));
        if ($result['data']['status'] !== $attributes['status']) throw new RuntimeException('A plataforma não confirmou o status solicitado.');
        return $result;
    }

    private function normalizeProduct(array $raw): array
    {
        return [
            'id' => (string) ($raw['id'] ?? ''),
            'sku' => $raw['sku'] ?? '',
            'name' => $raw['name'] ?? '',
            'price' => $raw['price'] ?? null,
            'stock_quantity' => $raw['stock_quantity'] ?? null,
            'status' => $raw['status'] ?? null,
            'created_at' => $raw['date_created'] ?? null,
            'raw' => $raw,
        ] + \App\Services\PlatformApi\ResourceDetails::product($raw, 'woocommerce', $this->storeUrl);
    }

    private function normalizeOrder(array $raw): array
    {
        return [
            'id' => (string) ($raw['id'] ?? ''),
            'status' => $raw['status'] ?? null,
            'customer' => trim(($raw['billing']['first_name'] ?? '') . ' ' . ($raw['billing']['last_name'] ?? '')),
            'customer_email' => $raw['billing']['email'] ?? null,
            'total' => $raw['total'] ?? null,
            'created_at' => $raw['date_created'] ?? null,
            'raw' => $raw,
        ] + \App\Services\PlatformApi\ResourceDetails::order($raw, 'woocommerce');
    }

    private function request(string $method, string $uri, array $options = []): array
    {
        $started = microtime(true);
        try {
            $response = $this->client->request($method, $uri, $options);
            $body = json_decode((string) $response->getBody(), true) ?? [];
            $this->lastResponse = ['http_status' => $response->getStatusCode(), 'duration_ms' => (int) ((microtime(true) - $started) * 1000), 'body' => \App\Support\Redactor::clean($body)];

            return [
                'body' => $body,
                'headers' => $response->getHeaders(),
                'status' => $response->getStatusCode(),
            ];
        } catch (GuzzleException $e) {
            $this->lastResponse = ['http_status' => $e instanceof \GuzzleHttp\Exception\RequestException ? $e->getResponse()?->getStatusCode() : null, 'duration_ms' => (int) ((microtime(true) - $started) * 1000), 'error' => \App\Support\Redactor::clean($e->getMessage())];
            throw new RuntimeException("WooCommerce API error: {$e->getMessage()}", 0, $e);
        }
    }
}
