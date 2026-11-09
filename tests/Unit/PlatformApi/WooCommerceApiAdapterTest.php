<?php

namespace Tests\Unit\PlatformApi;

use App\Services\PlatformApi\Adapters\WooCommerceApiAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class WooCommerceApiAdapterTest extends TestCase
{
    private function makeClient(array $responses): Client
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);

        return new Client(['handler' => $stack]);
    }

    private function makeAdapter(array $responses): WooCommerceApiAdapter
    {
        return new WooCommerceApiAdapter(
            storeUrl: 'https://example.com',
            consumerKey: 'ck_test',
            consumerSecret: 'cs_test',
            client: $this->makeClient($responses),
        );
    }

    public function test_list_products_normalizes_response(): void
    {
        $raw = [
            [
                'id' => 10,
                'sku' => 'SHIRT-001',
                'name' => 'Blue Shirt',
                'price' => '29.90',
                'stock_quantity' => 5,
                'status' => 'publish',
                'date_created' => '2026-01-01T00:00:00',
            ],
        ];

        $adapter = $this->makeAdapter([
            new Response(200, ['X-WP-Total' => ['1']], json_encode($raw)),
        ]);

        $result = $adapter->listProducts([], 1, 10);

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['data']);

        $product = $result['data'][0];
        $this->assertSame('10', $product['id']);
        $this->assertSame('SHIRT-001', $product['sku']);
        $this->assertSame('Blue Shirt', $product['name']);
        $this->assertSame('29.90', $product['price']);
        $this->assertSame(5, $product['stock_quantity']);
        $this->assertSame('publish', $product['status']);
        $this->assertSame($raw[0], $product['raw']);
    }

    public function test_list_products_passes_filters_as_query(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([])),
        ]);
        $stack = HandlerStack::create($mock);
        $historyContainer = [];
        $history = \GuzzleHttp\Middleware::history($historyContainer);
        $stack->push($history);

        $adapter = new WooCommerceApiAdapter(
            storeUrl: 'https://example.com',
            consumerKey: 'ck_test',
            consumerSecret: 'cs_test',
            client: new Client(['handler' => $stack]),
        );

        $adapter->listProducts(['sku' => 'SHIRT-001', 'status' => 'publish', 'category' => '12'], 2, 25);

        $request = $historyContainer[0]['request'];
        parse_str($request->getUri()->getQuery(), $query);

        $this->assertSame('SHIRT-001', $query['sku']);
        $this->assertSame('publish', $query['status']);
        $this->assertSame('2', $query['page']);
        $this->assertSame('25', $query['per_page']);
        $this->assertSame('12', $query['category']);
    }

    public function test_update_product_sends_put_request(): void
    {
        $raw = ['id' => 10, 'sku' => 'SHIRT-001', 'name' => 'Updated', 'price' => '49.90', 'status' => 'publish', 'date_created' => null, 'stock_quantity' => null];

        $mock = new MockHandler([new Response(200, [], json_encode($raw))]);
        $stack = HandlerStack::create($mock);
        $history = [];
        $stack->push(\GuzzleHttp\Middleware::history($history));

        $adapter = new WooCommerceApiAdapter(
            storeUrl: 'https://example.com',
            consumerKey: 'ck_test',
            consumerSecret: 'cs_test',
            client: new Client(['handler' => $stack]),
        );

        $result = $adapter->updateProduct('10', ['name' => 'Updated', 'price' => '49.90']);

        $this->assertSame('PUT', $history[0]['request']->getMethod());
        $this->assertSame('Updated', $result['data']['name']);
    }

    public function test_list_orders_normalizes_response(): void
    {
        $raw = [
            [
                'id' => 55,
                'status' => 'processing',
                'billing' => ['first_name' => 'João', 'last_name' => 'Silva', 'email' => 'joao@example.com'],
                'total' => '99.00',
                'date_created' => '2026-01-15T10:00:00',
            ],
        ];

        $adapter = $this->makeAdapter([
            new Response(200, ['X-WP-Total' => ['1']], json_encode($raw)),
        ]);

        $result = $adapter->listOrders([], 1, 10);

        $order = $result['data'][0];
        $this->assertSame('55', $order['id']);
        $this->assertSame('processing', $order['status']);
        $this->assertSame('João Silva', $order['customer']);
        $this->assertSame('joao@example.com', $order['customer_email']);
        $this->assertSame('99.00', $order['total']);
    }

    public function test_throws_runtime_exception_on_api_failure(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/WooCommerce API error/');

        $adapter = $this->makeAdapter([
            new \GuzzleHttp\Exception\ConnectException('Connection refused', new \GuzzleHttp\Psr7\Request('GET', 'test')),
        ]);

        $adapter->listProducts([], 1, 10);
    }
}
