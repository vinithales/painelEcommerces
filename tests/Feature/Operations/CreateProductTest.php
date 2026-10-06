<?php

namespace Tests\Feature\Operations;

use App\Livewire\Stores\CreateStoreProduct;
use App\Models\Store;
use App\Models\User;
use App\Services\PlatformApi\Adapters\Magento2ApiAdapter;
use App\Services\PlatformApi\Adapters\WooCommerceApiAdapter;
use App\Services\PlatformApi\PlatformApiAdapterFactory;
use App\Services\PlatformApi\ProductImageFetcher;
use App\Services\PlatformApi\ProductsApiService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CreateProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_woocommerce_form_creates_a_draft_with_image_url_and_attributes(): void
    {
        $store = Store::create(['name' => 'Woo', 'platform' => 'woocommerce', 'store_url' => 'https://example.com']);
        $history = [];
        $adapter = new WooCommerceApiAdapter('https://example.com', 'key', 'secret', $this->client([
            ['id' => 71, 'sku' => 'CAM-1', 'name' => 'Camisa', 'images' => []],
        ], $history));
        $this->mock(PlatformApiAdapterFactory::class, fn ($mock) => $mock->shouldReceive('make')->andReturn($adapter));

        $this->actingAs(User::factory()->create(['role' => 'n1']))
            ->get(route('stores.products.create', $store))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('stores.products.create', $store))
            ->assertOk()
            ->assertSee('Adicionar atributo')
            ->assertSee('Selecionar arquivos');

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(CreateStoreProduct::class, ['store' => $store])
            ->set('form.name', 'Camisa')
            ->set('form.sku', 'CAM-1')
            ->set('form.price', '39.90')
            ->call('addAttribute')
            ->set('productAttributes.1.code', 'Cor')
            ->set('productAttributes.1.value', 'Azul, Verde')
            ->call('addImageUrl')
            ->set('imageUrls.2.src', 'https://cdn.example.com/camisa.jpg')
            ->set('imageUrls.2.alt', 'Camisa azul')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Produto')
            ->assertSet('createdProduct.sku', 'CAM-1');

        $this->assertSame('POST', $history[0]['request']->getMethod());
        $payload = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('draft', $payload['status']);
        $this->assertSame('39.90', $payload['regular_price']);
        $this->assertSame(['Azul', 'Verde'], $payload['attributes'][0]['options']);
        $this->assertSame('Camisa azul', $payload['images'][0]['alt']);
    }

    public function test_woocommerce_uploads_file_to_wordpress_and_attaches_it_to_the_new_product(): void
    {
        $woocommerceHistory = [];
        $mediaHistory = [];
        $adapter = new WooCommerceApiAdapter(
            'https://example.com', 'key', 'secret',
            $this->client([
                ['id' => 72, 'sku' => 'SKU-72', 'name' => 'Produto', 'images' => []],
                ['id' => 72, 'sku' => 'SKU-72', 'name' => 'Produto', 'images' => [['id' => 88]]],
            ], $woocommerceHistory),
            'editor', 'application-password',
            $this->client([['id' => 88], ['id' => 88]], $mediaHistory, 'https://example.com/wp-json/wp/v2/'),
        );

        $adapter->createProduct(['name' => 'Produto', 'sku' => 'SKU-72', 'price' => '10', 'status' => 'draft'], [[
            'filename' => 'foto.png', 'name' => 'Foto principal', 'alt' => 'Vista frontal', 'mime' => 'image/png', 'contents' => 'PNG-DATA',
        ]]);

        $this->assertCount(2, $woocommerceHistory);
        $this->assertSame('PUT', $woocommerceHistory[1]['request']->getMethod());
        $this->assertSame([['id' => 88]], json_decode((string) $woocommerceHistory[1]['request']->getBody(), true)['images']);
        $this->assertSame('/wp-json/wp/v2/media', $mediaHistory[0]['request']->getUri()->getPath());
        $this->assertSame('Vista frontal', json_decode((string) $mediaHistory[1]['request']->getBody(), true)['alt_text']);
    }

    public function test_magento_creates_product_then_uploads_image_with_attributes(): void
    {
        $store = Store::create(['name' => 'Magento', 'platform' => 'magento2', 'store_url' => 'https://example.com']);
        $history = [];
        $adapter = new Magento2ApiAdapter(
            'https://example.com', '', '', '', '', null, null,
            $this->client([
                ['items' => [['attribute_set_id' => 17, 'attribute_set_name' => 'Default', 'entity_type_id' => 4]]],
                ['id' => 73, 'sku' => 'SKU-73', 'name' => 'Produto M2', 'status' => 2],
                '91',
            ], $history, 'https://example.com/rest/V1/'),
        );

        $this->mock(PlatformApiAdapterFactory::class, fn ($mock) => $mock->shouldReceive('make')->andReturn($adapter));
        $this->mock(ProductImageFetcher::class, fn ($mock) => $mock->shouldReceive('fetch')
            ->once()
            ->with('https://cdn.example.com/foto.png', 'Frente', 'Foto')
            ->andReturn(['filename' => 'foto.png', 'name' => 'Foto', 'alt' => 'Frente', 'mime' => 'image/png', 'contents' => 'PNG-DATA']));

        $result = app(ProductsApiService::class)->create($store, [
            'name' => 'Produto M2', 'sku' => 'SKU-73', 'price' => '20', 'status' => 'disabled',
            'attribute_set_id' => 17, 'visibility' => 4,
            'extension_attributes' => ['stock_item' => ['qty' => 10, 'is_in_stock' => true]],
            'custom_attributes' => [['attribute_code' => 'color', 'value' => '52']],
        ], [['src' => 'https://cdn.example.com/foto.png', 'name' => 'Foto', 'alt' => 'Frente', 'label' => 'Frente do produto', 'position' => 3, 'role' => 'principal', 'disabled' => false]]);

        $this->assertSame([], $result['image_errors']);
        $this->assertStringContainsString('eav/attribute-sets/list', (string) $history[0]['request']->getUri());
        $product = json_decode((string) $history[1]['request']->getBody(), true)['product'];
        $this->assertSame('simple', $product['type_id']);
        $this->assertSame(2, $product['status']);
        $this->assertSame('52', $product['custom_attributes'][0]['value']);
        $this->assertSame(10, $product['extension_attributes']['stock_item']['qty']);
        $this->assertSame(17, $product['attribute_set_id']);
        $media = json_decode((string) $history[2]['request']->getBody(), true)['entry'];
        $this->assertSame('Frente do produto', $media['label']);
        $this->assertSame(3, $media['position']);
        $this->assertSame(['image', 'small_image', 'thumbnail'], $media['types']);
        $this->assertSame(base64_encode('PNG-DATA'), $media['content']['base64EncodedData']);
    }

    public function test_magento_rejects_an_attribute_set_from_another_entity_type(): void
    {
        $store = Store::create(['name' => 'Magento', 'platform' => 'magento2', 'store_url' => 'https://example.com']);
        $history = [];
        $adapter = new Magento2ApiAdapter('https://example.com', '', '', '', '', null, null,
            $this->client([['items' => [['attribute_set_id' => 4, 'attribute_set_name' => 'Cliente', 'entity_type_id' => 1]]]], $history, 'https://example.com/rest/V1/'));
        $this->mock(PlatformApiAdapterFactory::class, fn ($mock) => $mock->shouldReceive('make')->andReturn($adapter));

        try {
            app(ProductsApiService::class)->create($store, [
                'name' => 'Produto', 'sku' => 'SKU-1', 'price' => '10', 'status' => 'disabled',
                'attribute_set_id' => 4, 'visibility' => 4,
            ]);
            $this->fail('O conjunto de outra entidade deveria ser rejeitado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('form.attribute_set_id', $e->errors());
        }

        $this->assertCount(1, $history);
    }

    private function client(array $responses, array &$history, string $baseUri = 'https://example.com/wp-json/wc/v3/'): Client
    {
        $stack = HandlerStack::create(new MockHandler(array_map(
            static fn ($body) => new Response(200, [], json_encode($body)),
            $responses,
        )));
        $stack->push(Middleware::history($history));

        return new Client(['handler' => $stack, 'base_uri' => $baseUri]);
    }
}
