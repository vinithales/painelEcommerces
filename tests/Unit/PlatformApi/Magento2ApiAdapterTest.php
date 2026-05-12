<?php

namespace Tests\Unit\PlatformApi;

use App\Services\PlatformApi\Adapters\Magento2ApiAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class Magento2ApiAdapterTest extends TestCase
{
    private function makeAdapter(array $responses): Magento2ApiAdapter
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $client = new Client(['handler' => $stack]);

        return new Magento2ApiAdapter(
            storeUrl: 'https://m2.example.com',
            consumerKey: 'ck_test',
            consumerSecret: 'cs_test',
            accessToken: 'at_test',
            accessTokenSecret: 'ats_test',
            client: $client,
        );
    }

    public function test_list_products_normalizes_response(): void
    {
        $raw = [
            'items' => [
                [
                    'id' => 42,
                    'sku' => 'M2-SHIRT-001',
                    'name' => 'M2 Blue Shirt',
                    'price' => 39.90,
                    'status' => 1,
                    'created_at' => '2026-01-01 00:00:00',
                    'extension_attributes' => ['stock_item' => ['qty' => 10]],
                ],
            ],
            'total_count' => 1,
        ];

        $adapter = $this->makeAdapter([
            new Response(200, [], json_encode($raw)),
        ]);

        $result = $adapter->listProducts([], 1, 10);

        $this->assertSame(1, $result['total']);
        $product = $result['data'][0];
        $this->assertSame('42', $product['id']);
        $this->assertSame('M2-SHIRT-001', $product['sku']);
        $this->assertSame('enabled', $product['status']);
        $this->assertEqualsWithDelta(10.0, $product['stock_quantity'], 0.001);
    }

    public function test_list_orders_normalizes_response(): void
    {
        $raw = [
            'items' => [
                [
                    'entity_id' => 99,
                    'status' => 'complete',
                    'customer_firstname' => 'Maria',
                    'customer_lastname' => 'Santos',
                    'customer_email' => 'maria@example.com',
                    'grand_total' => 150.00,
                    'created_at' => '2026-02-01 08:00:00',
                ],
            ],
            'total_count' => 1,
        ];

        $adapter = $this->makeAdapter([
            new Response(200, [], json_encode($raw)),
        ]);

        $result = $adapter->listOrders([], 1, 10);

        $order = $result['data'][0];
        $this->assertSame('99', $order['id']);
        $this->assertSame('complete', $order['status']);
        $this->assertSame('Maria Santos', $order['customer']);
        $this->assertSame('150', (string) $order['total']);
    }

    public function test_update_product_wraps_payload_in_product_key(): void
    {
        $raw = ['id' => 42, 'sku' => 'M2-SHIRT-001', 'name' => 'Updated', 'price' => 49.90, 'status' => 1, 'created_at' => null];

        $history = [];
        $mock = new MockHandler([new Response(200, [], json_encode($raw))]);
        $stack = HandlerStack::create($mock);
        $stack->push(\GuzzleHttp\Middleware::history($history));

        $adapter = new Magento2ApiAdapter(
            storeUrl: 'https://m2.example.com',
            consumerKey: 'ck_test',
            consumerSecret: 'cs_test',
            accessToken: 'at_test',
            accessTokenSecret: 'ats_test',
            client: new Client(['handler' => $stack]),
        );

        $adapter->updateProduct('42', ['name' => 'Updated', 'price' => 49.90]);

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertArrayHasKey('product', $body);
        $this->assertSame('Updated', $body['product']['name']);
    }

    public function test_throws_runtime_exception_on_api_failure(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Magento 2 API error/');

        $adapter = $this->makeAdapter([
            new \GuzzleHttp\Exception\ConnectException('Connection refused', new \GuzzleHttp\Psr7\Request('GET', 'test')),
        ]);

        $adapter->listProducts([], 1, 10);
    }
}
