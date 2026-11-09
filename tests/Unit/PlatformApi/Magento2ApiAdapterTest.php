<?php

namespace Tests\Unit\PlatformApi;

use App\Models\Store;
use App\Services\PlatformApi\Adapters\Magento2ApiAdapter;
use App\Services\PlatformApi\ProductFields;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
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

    public function test_category_filter_is_combined_with_sku_on_the_server(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{"items":[],"total_count":0}')]));
        $stack->push(\GuzzleHttp\Middleware::history($history));
        $adapter = new Magento2ApiAdapter('https://example.com', 'ck', 'cs', 'at', 'ats', client: new Client(['handler' => $stack]));
        $adapter->listProducts(['category' => '12', 'sku' => 'ABC'], 2, 25);
        parse_str($history[0]['request']->getUri()->getQuery(), $query);
        $groups = $query['searchCriteria']['filterGroups'];
        $this->assertSame(['field' => 'category_id', 'value' => '12', 'conditionType' => 'eq'], $groups[0]['filters'][0]);
        $this->assertSame('sku', $groups[1]['filters'][0]['field']);
        $this->assertSame('ABC', $groups[1]['filters'][0]['value']);
    }

    public function test_categories_include_subsequent_pages(): void
    {
        $adapter = $this->makeAdapter([
            new Response(200, [], json_encode(['items' => [['id' => 12, 'name' => 'Camisetas']], 'total_count' => 101])),
            new Response(200, [], json_encode(['items' => [['id' => 13, 'name' => 'Sapatos']], 'total_count' => 101])),
        ]);
        $this->assertSame([
            ['id' => '12', 'name' => 'Camisetas'], ['id' => '13', 'name' => 'Sapatos'],
        ], $adapter->productCategories());
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
        $stack->push(Middleware::history($history));

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
            new ConnectException('Connection refused', new Request('GET', 'test')),
        ]);

        $adapter->listProducts([], 1, 10);
    }

    public function test_image_attributes_are_translated_to_media_gallery_entries(): void
    {
        $payload = ProductFields::payload('magento2', ['images' => [[
            'id' => 709,
            'src' => 'https://m2.example.com/media/catalog/product/m/j/image.jpg',
            'name' => 'image.jpg',
            'alt' => 'Imagem principal',
            'position' => 1,
            'disabled' => false,
            'types' => ['image', 'small_image', 'thumbnail'],
            'media_type' => 'image',
            'file' => '/m/j/image.jpg',
            'date_created' => null,
        ]]]);

        $this->assertArrayNotHasKey('images', $payload);
        $this->assertSame([[
            'id' => 709,
            'media_type' => 'image',
            'label' => 'Imagem principal',
            'position' => 1,
            'disabled' => false,
            'types' => ['image', 'small_image', 'thumbnail'],
            'file' => '/m/j/image.jpg',
        ]], $payload['media_gallery_entries']);
    }

    public function test_magento_extension_image_roles_are_preserved(): void
    {
        $store = new Store(['platform' => Store::PLATFORM_MAGENTO2]);
        $images = [[
            'id' => 710,
            'src' => 'https://m2.example.com/media/catalog/product/m/j/image.jpg',
            'name' => 'image.jpg',
            'alt' => 'Imagem secundária',
            'position' => 2,
            'disabled' => false,
            'types' => ['swatch_image', 'amasty_conf_flipper_image', 'flipper_img'],
            'media_type' => 'image',
            'file' => '/m/j/image.jpg',
            'date_created' => null,
        ]];

        $validated = ProductFields::validate($store, ['images' => $images]);

        $this->assertSame(
            ['swatch_image', 'amasty_conf_flipper_image', 'flipper_img'],
            $validated['images'][0]['types']
        );
    }
}
