<?php

namespace App\Services\PlatformApi\Adapters;

use App\Services\PlatformApi\Contracts\PlatformApiAdapter;
use App\Services\PlatformApi\OrderTransitions;
use App\Services\PlatformApi\ProductFields;
use App\Services\PlatformApi\ResourceDetails;
use App\Support\Redactor;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

class WooCommerceApiAdapter implements PlatformApiAdapter
{
    private Client $client;

    private array $lastResponse = [];

    public function telemetry(): array
    {
        return $this->lastResponse;
    }

    public function storeInformation(): array
    {
        return $this->request('GET', 'system_status')['body'];
    }

    public function __construct(
        private readonly string $storeUrl,
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
        ?Client $client = null,
        private readonly ?string $wpUsername = null,
        private readonly ?string $wpApplicationPassword = null,
        private readonly ?Client $mediaClient = null,
    ) {
        $this->client = $client ?? new Client([
            'base_uri' => rtrim($storeUrl, '/').'/wp-json/wc/v3/',
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
            'category' => $filters['category'] ?? null,
            'status' => $filters['status'] ?? null,
            'after' => empty($filters['date_from']) ? null : $filters['date_from'].'T00:00:00',
            'before' => empty($filters['date_to']) ? null : $filters['date_to'].'T23:59:59',
            'include' => isset($filters['ids']) ? implode(',', $filters['ids']) : null,
            'search' => $filters['name'] ?? null,
            'search_sku' => empty($filters['sku']) ? ($filters['sku_prefix'] ?? null) : null,
            'orderby' => ($filters['sort'] ?? '') === 'name' ? 'title' : ($filters['sort'] ?? 'id'),
            'order' => $filters['direction'] ?? 'asc',
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->request('GET', 'products', ['query' => $query]);
        $raw = $response['body'];
        $total = (int) ($response['headers']['X-WP-Total'][0] ?? count($raw));

        $items = array_map(fn (array $p) => $this->normalizeProduct($p), $raw);
        if (! empty($filters['sku_prefix'])) {
            $items = array_values(array_filter($items, fn ($p) => str_starts_with(strtolower($p['sku']), strtolower($filters['sku_prefix']))));
        }

        return [
            'data' => $items,
            'raw' => $raw,
            'total' => $total,
        ];
    }

    public function productCategories(): array
    {
        $categories = [];
        $page = 1;
        do {
            $response = $this->request('GET', 'products/categories', ['query' => [
                'page' => $page, 'per_page' => 100, 'hide_empty' => 'false', 'orderby' => 'name', 'order' => 'asc',
            ]]);
            foreach ($response['body'] as $category) {
                $categories[] = ['id' => (string) $category['id'], 'name' => $category['name']];
            }
            $pages = (int) ($response['headers']['X-WP-TotalPages'][0] ?? (count($response['body']) === 100 ? $page + 1 : $page));
        } while (++$page <= $pages);

        return $categories;
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

    public function createProduct(array $attributes, array $images = []): array
    {
        $files = array_values(array_filter($images, static fn ($image) => isset($image['contents'])));
        if ($files && (! $this->wpUsername || ! $this->wpApplicationPassword || parse_url($this->storeUrl, PHP_URL_SCHEME) !== 'https')) {
            throw new RuntimeException('Para enviar arquivos ao WooCommerce, configure usuário e senha de aplicativo WordPress em uma loja HTTPS.');
        }

        $payload = ProductFields::payload('woocommerce', $attributes);
        $payload['type'] = 'simple';
        $payload['images'] = array_values(array_filter($images, static fn ($image) => isset($image['src'])));
        $raw = $this->request('POST', 'products', ['json' => $payload])['body'];
        $imageErrors = [];
        $uploadedIds = [];

        if ($files) {
            $client = $this->mediaClient ?? new Client([
                'base_uri' => rtrim($this->storeUrl, '/').'/wp-json/wp/v2/',
                'auth' => [$this->wpUsername, $this->wpApplicationPassword],
                'timeout' => 30,
                'verify' => true,
                'allow_redirects' => false,
            ]);

            foreach ($files as $file) {
                try {
                    $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['filename']);
                    $media = json_decode((string) $client->request('POST', 'media', [
                        'headers' => ['Content-Type' => $file['mime'], 'Content-Disposition' => 'attachment; filename="'.$name.'"'],
                        'body' => $file['contents'],
                    ])->getBody(), true);
                    $id = $media['id'] ?? null;
                    if (! $id) {
                        throw new RuntimeException('O WordPress não retornou o ID da mídia.');
                    }
                    $client->request('POST', 'media/'.$id, ['json' => [
                        'alt_text' => $file['alt'] ?? '',
                        'title' => $file['name'],
                        'post' => $raw['id'],
                    ]]);
                    $uploadedIds[] = (int) $id;
                } catch (\Throwable $e) {
                    $imageErrors[] = $file['name'].': '.Redactor::clean($e->getMessage());
                }
            }
        }

        if ($uploadedIds) {
            try {
                $existing = array_map(static fn ($image) => ['id' => (int) $image['id']], $raw['images'] ?? []);
                $new = array_map(static fn ($id) => ['id' => $id], $uploadedIds);
                $raw = $this->request('PUT', 'products/'.rawurlencode((string) $raw['id']), ['json' => ['images' => array_merge($existing, $new)]])['body'];
            } catch (\Throwable $e) {
                $imageErrors[] = 'As mídias foram enviadas, mas não vinculadas ao produto: '.Redactor::clean($e->getMessage());
            }
        }

        return ['data' => $this->normalizeProduct($raw), 'raw' => $raw, 'image_errors' => $imageErrors];
    }

    public function updateProduct(string $id, array $attributes): array
    {
        $id = rawurlencode($id);
        $attributes = ProductFields::payload('woocommerce', $attributes);
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
            'after' => empty($filters['date_from']) ? null : $filters['date_from'].'T00:00:00',
            'before' => empty($filters['date_to']) ? null : $filters['date_to'].'T23:59:59',
            'search' => $filters['customer_email'] ?? null,
            'include' => ! empty($filters['number']) ? $filters['number'] : null,
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
        OrderTransitions::validateWoo($current['data'], $attributes);
        $id = rawurlencode($id);
        $response = $this->request('PUT', "orders/{$id}", ['json' => $attributes]);
        $result = $this->getOrder(rawurldecode($id));
        if ($result['data']['status'] !== $attributes['status']) {
            throw new RuntimeException('A plataforma não confirmou o status solicitado.');
        }

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
        ] + ResourceDetails::product($raw, 'woocommerce', $this->storeUrl);
    }

    private function normalizeOrder(array $raw): array
    {
        return [
            'id' => (string) ($raw['id'] ?? ''),
            'status' => $raw['status'] ?? null,
            'customer' => trim(($raw['billing']['first_name'] ?? '').' '.($raw['billing']['last_name'] ?? '')),
            'customer_email' => $raw['billing']['email'] ?? null,
            'total' => $raw['total'] ?? null,
            'created_at' => $raw['date_created'] ?? null,
            'raw' => $raw,
        ] + ResourceDetails::order($raw, 'woocommerce');
    }

    private function request(string $method, string $uri, array $options = []): array
    {
        $started = microtime(true);
        try {
            $response = $this->client->request($method, $uri, $options);
            $body = json_decode((string) $response->getBody(), true) ?? [];
            $this->lastResponse = ['http_status' => $response->getStatusCode(), 'duration_ms' => (int) ((microtime(true) - $started) * 1000), 'body' => Redactor::clean($body)];
            if (json_last_error() !== JSON_ERROR_NONE || $response->getStatusCode() >= 300) {
                throw new RuntimeException('WooCommerce API error: resposta JSON/HTTP inesperada.');
            }

            return [
                'body' => $body,
                'headers' => $response->getHeaders(),
                'status' => $response->getStatusCode(),
            ];
        } catch (GuzzleException $e) {
            $this->lastResponse = ['http_status' => $e instanceof RequestException ? $e->getResponse()?->getStatusCode() : null, 'duration_ms' => (int) ((microtime(true) - $started) * 1000), 'error' => Redactor::clean($e->getMessage())];
            throw new RuntimeException("WooCommerce API error: {$e->getMessage()}", 0, $e);
        }
    }
}
