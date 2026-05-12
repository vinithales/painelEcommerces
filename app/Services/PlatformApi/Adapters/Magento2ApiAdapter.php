<?php

namespace App\Services\PlatformApi\Adapters;

use App\Services\PlatformApi\Contracts\PlatformApiAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

class Magento2ApiAdapter implements PlatformApiAdapter
{
    private Client $client;

    private ?string $bearerToken = null;

    public function __construct(
        private readonly string $storeUrl,
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
        private readonly string $accessToken,
        private readonly string $accessTokenSecret,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        ?Client $client = null,
    ) {
        if ($client !== null) {
            $this->client = $client;
        } else {
            $stack = HandlerStack::create();
            $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
                if ($this->usesBearerToken()) {
                    $token = $this->bearerToken ??= $this->fetchBearerToken();

                    return $request->withHeader('Authorization', 'Bearer ' . $token);
                }

                return $request->withHeader('Authorization', $this->buildOAuthHeader($request));
            }));

            $this->client = new Client([
                'base_uri' => rtrim($storeUrl, '/') . '/rest/V1/',
                'handler' => $stack,
                'timeout' => 30,
                'verify' => false,
            ]);
        }
    }

    private function usesBearerToken(): bool
    {
        return filled($this->username) && filled($this->password);
    }

    private function fetchBearerToken(): string
    {
        $tokenClient = new Client(['timeout' => 30, 'verify' => false]);

        try {
            $response = $tokenClient->post(
                rtrim($this->storeUrl, '/') . '/rest/V1/integration/admin/token',
                ['json' => ['username' => $this->username, 'password' => $this->password]],
            );
        } catch (GuzzleException $e) {
            throw new RuntimeException('Failed to obtain Magento 2 bearer token: ' . $e->getMessage(), 0, $e);
        }

        $token = json_decode((string) $response->getBody(), true);

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Magento 2 bearer token response was unexpected.');
        }

        return $token;
    }

    private function buildOAuthHeader(RequestInterface $request): string
    {
        $params = [
            'oauth_consumer_key' => $this->consumerKey,
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA256',
            'oauth_timestamp' => (string) time(),
            'oauth_token' => $this->accessToken,
            'oauth_version' => '1.0',
        ];

        $url = (string) $request->getUri()->withQuery('')->withFragment('');
        $queryString = $request->getUri()->getQuery();
        $queryParams = [];
        if ($queryString !== '') {
            parse_str($queryString, $queryParams);
        }

        $allParams = array_merge($params, $queryParams);
        ksort($allParams);

        $paramString = http_build_query($allParams, '', '&', PHP_QUERY_RFC3986);
        $baseString = strtoupper($request->getMethod()) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);
        $signingKey = rawurlencode($this->consumerSecret) . '&' . rawurlencode($this->accessTokenSecret);

        $params['oauth_signature'] = base64_encode(hash_hmac('sha256', $baseString, $signingKey, true));

        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
        }

        return 'OAuth ' . implode(', ', $parts);
    }

    public function listProducts(array $filters, int $page, int $perPage): array
    {
        $query = $this->buildSearchCriteria($filters, $page, $perPage, 'products');

        $response = $this->request('GET', 'products', ['query' => $query]);
        $raw = $response['body'];
        $items = $raw['items'] ?? [];
        $total = (int) ($raw['total_count'] ?? count($items));

        return [
            'data' => array_map(fn (array $p) => $this->normalizeProduct($p), $items),
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
        $response = $this->request('PUT', "products/{$id}", ['json' => ['product' => $attributes]]);
        $raw = $response['body'];

        return [
            'data' => $this->normalizeProduct($raw),
            'raw' => $raw,
        ];
    }

    public function listOrders(array $filters, int $page, int $perPage): array
    {
        $query = $this->buildSearchCriteria($filters, $page, $perPage, 'orders');

        $response = $this->request('GET', 'orders', ['query' => $query]);
        $raw = $response['body'];
        $items = $raw['items'] ?? [];
        $total = (int) ($raw['total_count'] ?? count($items));

        return [
            'data' => array_map(fn (array $o) => $this->normalizeOrder($o), $items),
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
        $response = $this->request('PUT', "orders/{$id}", ['json' => ['entity' => $attributes]]);
        $raw = $response['body'];

        return [
            'data' => $this->normalizeOrder($raw),
            'raw' => $raw,
        ];
    }

    private function normalizeProduct(array $raw): array
    {
        $price = null;
        foreach ($raw['custom_attributes'] ?? [] as $attr) {
            if ($attr['attribute_code'] === 'price') {
                $price = $attr['value'];
                break;
            }
        }

        return [
            'id' => (string) ($raw['id'] ?? ''),
            'sku' => $raw['sku'] ?? '',
            'name' => $raw['name'] ?? '',
            'price' => $raw['price'] ?? $price,
            'stock_quantity' => $raw['extension_attributes']['stock_item']['qty'] ?? null,
            'status' => $raw['status'] === 1 ? 'enabled' : ($raw['status'] === 2 ? 'disabled' : null),
            'created_at' => $raw['created_at'] ?? null,
            'raw' => $raw,
        ];
    }

    private function normalizeOrder(array $raw): array
    {
        return [
            'id' => (string) ($raw['entity_id'] ?? $raw['id'] ?? ''),
            'status' => $raw['status'] ?? null,
            'customer' => trim(($raw['customer_firstname'] ?? '') . ' ' . ($raw['customer_lastname'] ?? '')),
            'customer_email' => $raw['customer_email'] ?? null,
            'total' => $raw['grand_total'] ?? null,
            'created_at' => $raw['created_at'] ?? null,
            'raw' => $raw,
        ];
    }

    private function buildSearchCriteria(array $filters, int $page, int $perPage, string $context): array
    {
        $query = [
            'searchCriteria[pageSize]' => $perPage,
            'searchCriteria[currentPage]' => $page,
        ];

        $filterIndex = 0;

        if ($context === 'products') {
            if (! empty($filters['sku'])) {
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][field]"] = 'sku';
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][value]"] = $filters['sku'];
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][conditionType]"] = 'eq';
                $filterIndex++;
            }

            if (! empty($filters['sku_prefix'])) {
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][field]"] = 'sku';
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][value]"] = $filters['sku_prefix'] . '%';
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][conditionType]"] = 'like';
                $filterIndex++;
            }

            if (! empty($filters['status'])) {
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][field]"] = 'status';
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][value]"] = $filters['status'] === 'enabled' ? '1' : '2';
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][conditionType]"] = 'eq';
                $filterIndex++;
            }
        }

        if ($context === 'orders') {
            if (! empty($filters['status'])) {
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][field]"] = 'status';
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][value]"] = $filters['status'];
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][conditionType]"] = 'eq';
                $filterIndex++;
            }

            if (! empty($filters['customer_email'])) {
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][field]"] = 'customer_email';
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][value]"] = $filters['customer_email'];
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][conditionType]"] = 'eq';
                $filterIndex++;
            }
        }

        if (! empty($filters['date_from'])) {
            $dateField = $context === 'products' ? 'created_at' : 'created_at';
            $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][field]"] = $dateField;
            $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][value]"] = $filters['date_from'];
            $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][conditionType]"] = 'gteq';
            $filterIndex++;
        }

        if (! empty($filters['date_to'])) {
            $dateField = $context === 'products' ? 'created_at' : 'created_at';
            $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][field]"] = $dateField;
            $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][value]"] = $filters['date_to'];
            $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][conditionType]"] = 'lteq';
        }

        return $query;
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
            throw new RuntimeException("Magento 2 API error: {$e->getMessage()}", 0, $e);
        }
    }
}
