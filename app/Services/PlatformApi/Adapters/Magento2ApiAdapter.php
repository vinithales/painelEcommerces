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
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

class Magento2ApiAdapter implements PlatformApiAdapter
{
    private Client $client;

    private array $lastResponse = [];

    public function telemetry(): array
    {
        return $this->lastResponse;
    }

    public function storeInformation(): array
    {
        return $this->request('GET', 'store/storeConfigs')['body'];
    }

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

                    return $request->withHeader('Authorization', 'Bearer '.$token);
                }

                return $request->withHeader('Authorization', $this->buildOAuthHeader($request));
            }));

            $this->client = new Client([
                'base_uri' => rtrim($storeUrl, '/').'/rest/V1/',
                'handler' => $stack,
                'timeout' => 30,
                'verify' => true,
                'allow_redirects' => false,
            ]);
        }
    }

    private function usesBearerToken(): bool
    {
        return filled($this->username) && filled($this->password);
    }

    private function fetchBearerToken(): string
    {
        $tokenClient = new Client(['timeout' => 30, 'verify' => true, 'allow_redirects' => false]);

        try {
            $response = $tokenClient->post(
                rtrim($this->storeUrl, '/').'/rest/V1/integration/admin/token',
                ['json' => ['username' => $this->username, 'password' => $this->password]],
            );
        } catch (GuzzleException $e) {
            throw new RuntimeException('Failed to obtain Magento 2 bearer token: '.$e->getMessage(), 0, $e);
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
        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = [rawurlencode($key), rawurlencode($value)];
        }
        foreach (array_filter(explode('&', $queryString)) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $pairs[] = [rawurlencode(urldecode($key)), rawurlencode(urldecode($value))];
        }
        usort($pairs, fn ($a, $b) => strcmp($a[0], $b[0]) ?: strcmp($a[1], $b[1]));
        $paramString = implode('&', array_map(fn ($pair) => $pair[0].'='.$pair[1], $pairs));
        $baseString = strtoupper($request->getMethod()).'&'.rawurlencode($url).'&'.rawurlencode($paramString);
        $signingKey = rawurlencode($this->consumerSecret).'&'.rawurlencode($this->accessTokenSecret);

        $params['oauth_signature'] = base64_encode(hash_hmac('sha256', $baseString, $signingKey, true));

        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = rawurlencode($key).'="'.rawurlencode($value).'"';
        }

        return 'OAuth '.implode(', ', $parts);
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

    public function productCategories(): array
    {
        $categories = [];
        $page = 1;
        do {
            $body = $this->request('GET', 'categories/list', ['query' => [
                'searchCriteria[pageSize]' => 100,
                'searchCriteria[currentPage]' => $page,
                'searchCriteria[sortOrders][0][field]' => 'name',
                'searchCriteria[sortOrders][0][direction]' => 'ASC',
            ]])['body'];
            foreach ($body['items'] ?? [] as $category) {
                $categories[] = ['id' => (string) $category['id'], 'name' => $category['name']];
            }
        } while (! empty($body['items']) && $page++ * 100 < ($body['total_count'] ?? 0));

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

    public function productAttributeSets(): array
    {
        $sets = $this->request('GET', 'eav/attribute-sets/list', ['query' => [
            'searchCriteria[pageSize]' => 100,
            'searchCriteria[currentPage]' => 1,
            'searchCriteria[filterGroups][0][filters][0][field]' => 'entity_type_id',
            'searchCriteria[filterGroups][0][filters][0][value]' => 4,
            'searchCriteria[filterGroups][0][filters][0][conditionType]' => 'eq',
        ]])['body']['items'] ?? [];

        return array_values(array_filter($sets, static fn (array $set): bool =>
            (int) ($set['entity_type_id'] ?? 0) === 4 && (int) ($set['attribute_set_id'] ?? 0) > 0
        ));
    }

    public function createProduct(array $attributes, array $images = []): array
    {
        $attributes = ProductFields::payload('magento2', $attributes);
        $attributes['type_id'] = 'simple';
        $raw = $this->request('POST', 'products', ['json' => ['product' => $attributes]])['body'];
        $imageErrors = [];

        foreach ($images as $position => $image) {
            try {
                $this->request('POST', 'products/'.rawurlencode($raw['sku']).'/media', ['json' => [
                    'entry' => [
                        'media_type' => 'image',
                        'label' => ($image['label'] ?? '') ?: ($image['alt'] ?: $image['name']),
                        'position' => ($image['position'] ?? '') ?: $position + 1,
                        'disabled' => (bool) ($image['disabled'] ?? false),
                        'types' => ($image['role'] ?? ($position === 0 ? 'principal' : 'galeria')) === 'principal'
                            ? ['image', 'small_image', 'thumbnail'] : [],
                        'content' => [
                            'base64EncodedData' => base64_encode($image['contents']),
                            'type' => $image['mime'],
                            'name' => $image['filename'],
                        ],
                    ],
                ]]);
            } catch (\Throwable $e) {
                $imageErrors[] = $image['name'].': '.\App\Support\Redactor::clean($e->getMessage());
            }
        }

        return ['data' => $this->normalizeProduct($raw), 'raw' => $raw, 'image_errors' => $imageErrors];
    }

    public function updateProduct(string $id, array $attributes): array
    {
        $id = rawurlencode($id);
        $attributes = ProductFields::payload('magento2', $attributes);
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
        if (isset($attributes['status']) || isset($attributes['state'])) {
            throw new RuntimeException('Magento exige uma operação explícita; state/status não são graváveis.');
        }
        $current = $this->getOrder($id);
        $operation = OrderTransitions::magentoOperation($current['data'], $attributes);
        $response = $this->request('POST', 'orders/'.rawurlencode($id).'/'.$operation);
        if ($response['body'] !== true) {
            throw new RuntimeException('Magento recusou a operação.');
        }
        $result = $this->getOrder($id);
        $state = $result['data']['state'];
        $valid = match ($operation) {
            'cancel' => $state === 'canceled', 'hold' => $state === 'holded', 'unhold' => ! in_array($state, ['holded', 'canceled', 'closed'], true)
        };
        if (! $valid) {
            throw new RuntimeException('Magento não confirmou o estado final.');
        }

        return $result;
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
            'status' => (int) ($raw['status'] ?? 0) === 1 ? 'enabled' : ((int) ($raw['status'] ?? 0) === 2 ? 'disabled' : null),
            'created_at' => $raw['created_at'] ?? null,
            'raw' => $raw,
        ] + ResourceDetails::product($raw, 'magento2', $this->storeUrl);
    }

    private function normalizeOrder(array $raw): array
    {
        return [
            'id' => (string) ($raw['entity_id'] ?? $raw['id'] ?? ''),
            'status' => $raw['status'] ?? null,
            'customer' => trim(($raw['customer_firstname'] ?? '').' '.($raw['customer_lastname'] ?? '')),
            'customer_email' => $raw['customer_email'] ?? null,
            'total' => $raw['grand_total'] ?? null,
            'created_at' => $raw['created_at'] ?? null,
            'raw' => $raw,
        ] + ResourceDetails::order($raw, 'magento2');
    }

    private function buildSearchCriteria(array $filters, int $page, int $perPage, string $context): array
    {
        $query = [
            'searchCriteria[pageSize]' => $perPage,
            'searchCriteria[currentPage]' => $page,
        ];

        $filterIndex = 0;
        foreach (['name' => 'name', 'number' => 'increment_id', 'id' => 'entity_id'] as $key => $field) {
            if (! empty($filters[$key])) {
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][field]"] = $field;
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][value]"] = $key === 'name' ? '%'.$filters[$key].'%' : $filters[$key];
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][conditionType]"] = $key === 'name' ? 'like' : 'eq';
                $filterIndex++;
            }
        }
        if (! empty($filters['sort'])) {
            $query['searchCriteria[sortOrders][0][field]'] = $filters['sort'];
            $query['searchCriteria[sortOrders][0][direction]'] = strtoupper($filters['direction'] ?? 'asc');
        }

        if ($context === 'products') {
            if (! empty($filters['category'])) {
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][field]"] = 'category_id';
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][value]"] = $filters['category'];
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][conditionType]"] = 'eq';
                $filterIndex++;
            }

            if (! empty($filters['sku'])) {
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][field]"] = 'sku';
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][value]"] = $filters['sku'];
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][conditionType]"] = 'eq';
                $filterIndex++;
            }

            if (! empty($filters['sku_prefix'])) {
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][field]"] = 'sku';
                $query["searchCriteria[filterGroups][{$filterIndex}][filters][0][value]"] = $filters['sku_prefix'].'%';
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
        $started = microtime(true);
        try {
            $response = $this->client->request($method, $uri, $options);
            $body = json_decode((string) $response->getBody(), true) ?? [];
            $this->lastResponse = ['http_status' => $response->getStatusCode(), 'duration_ms' => (int) ((microtime(true) - $started) * 1000), 'body' => Redactor::clean($body)];
            if (json_last_error() !== JSON_ERROR_NONE || $response->getStatusCode() >= 300) {
                throw new RuntimeException('Magento 2 API error: resposta JSON/HTTP inesperada.');
            }

            return [
                'body' => $body,
                'headers' => $response->getHeaders(),
                'status' => $response->getStatusCode(),
            ];
        } catch (GuzzleException $e) {
            $this->lastResponse = ['http_status' => $e instanceof RequestException ? $e->getResponse()?->getStatusCode() : null, 'duration_ms' => (int) ((microtime(true) - $started) * 1000), 'error' => Redactor::clean($e->getMessage())];
            throw new RuntimeException("Magento 2 API error: {$e->getMessage()}", 0, $e);
        }
    }
}
