<?php

namespace App\Services\PlatformApi\Adapters;

use App\Services\PlatformApi\Contracts\PlatformApiAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class WooCommerceApiAdapter implements PlatformApiAdapter
{
    private Client $client;

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
            'verify' => false,
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
            'search' => $filters['sku_prefix'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->request('GET', 'products', ['query' => $query]);
        $raw = $response['body'];
        $total = (int) ($response['headers']['X-WP-Total'][0] ?? count($raw));

        return [
            'data' => array_map(fn (array $p) => $this->normalizeProduct($p), $raw),
            'raw' => $raw,
            'total' => $total,
        ];
    }

    public function getProduct(string $id): array
    {
        $response = $this->request('GET', "products/{$id}");
        $raw = $response['body'];

        return [
            'data' => $this->normalizeProduct($raw),
            'raw' => $raw,
        ];
    }

    public function updateProduct(string $id, array $attributes): array
    {
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
            'customer' => $filters['customer_email'] ?? null,
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
        $response = $this->request('GET', "orders/{$id}");
        $raw = $response['body'];

        return [
            'data' => $this->normalizeOrder($raw),
            'raw' => $raw,
        ];
    }

    public function updateOrder(string $id, array $attributes): array
    {
        $response = $this->request('PUT', "orders/{$id}", ['json' => $attributes]);
        $raw = $response['body'];

        return [
            'data' => $this->normalizeOrder($raw),
            'raw' => $raw,
        ];
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
        ];
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
        ];
    }

    private function request(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->client->request($method, $uri, $options);
            $body = json_decode((string) $response->getBody(), true) ?? [];

            return [
                'body' => $body,
                'headers' => $response->getHeaders(),
                'status' => $response->getStatusCode(),
            ];
        } catch (GuzzleException $e) {
            throw new RuntimeException("WooCommerce API error: {$e->getMessage()}", 0, $e);
        }
    }
}
