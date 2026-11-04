<?php

namespace Tests\Feature\Operations;

use App\Actions\Products\BulkUpdateProductsAction;
use App\Actions\Products\RollbackProductAction;
use App\Actions\Products\UpdateProductAction;
use App\Jobs\UpdateProductJob;
use App\Livewire\Stores\StoreProducts;
use App\Models\ProductSnapshot;
use App\Models\Store;
use App\Models\User;
use App\Services\PlatformApi\Adapters\WooCommerceApiAdapter;
use App\Services\PlatformApi\PlatformApiAdapterFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ProductWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function store(): Store
    {
        return Store::create(['name' => 'Loja', 'store_url' => 'https://example.com', 'platform' => 'woocommerce']);
    }

    private function fake(array $payloads, array &$history): void
    {
        $stack = HandlerStack::create(new MockHandler(array_map(fn ($p) => new Response(200, [], json_encode($p)), $payloads)));
        $stack->push(Middleware::history($history));
        $adapter = new WooCommerceApiAdapter('https://example.com', 'key', 'secret', new Client(['handler' => $stack]));
        $this->mock(PlatformApiAdapterFactory::class, fn ($mock) => $mock->shouldReceive('make')->andReturn($adapter));
    }

    private function product(string $price = '10'): array
    {
        return ['id' => 1, 'sku' => 'SKU-1', 'name' => 'Produto', 'price' => $price, 'regular_price' => $price, 'status' => 'publish', 'weight' => '1.2', 'images' => [['id' => 81, 'src' => 'https://example.com/image.jpg', 'name' => 'Imagem frontal', 'alt' => 'Frente azul']], 'permalink' => 'https://example.com/product/1'];
    }

    public function test_preview_never_writes_and_shows_only_changed_fields(): void
    {
        $history = [];
        $this->fake([$this->product()], $history);
        $r = app(UpdateProductAction::class)->execute($this->store(), '1', ['price' => '20', 'name' => 'Produto'], true);
        $this->assertSame(['price'], array_keys($r['diff']));
        $this->assertSame('GET', $history[0]['request']->getMethod());
        $this->assertCount(1, $history);
        $this->assertDatabaseCount('product_snapshots', 0);
    }

    public function test_partial_update_and_valid_snapshot_rollback(): void
    {
        $history = [];
        $this->fake([$this->product(), $this->product('20'), $this->product('20'), $this->product('20'), $this->product()], $history);
        $store = $this->store();
        app(UpdateProductAction::class)->execute($store, '1', ['price' => '20', 'name' => 'Produto']);
        $payload = json_decode((string) $history[1]['request']->getBody(), true);
        $this->assertSame(['regular_price' => '20'], $payload);
        $snapshot = ProductSnapshot::first();
        $this->assertSame(['price' => '10'], $snapshot->data_before);
        app(RollbackProductAction::class)->execute($snapshot);
        $this->assertSame(['regular_price' => '10'], json_decode((string) $history[4]['request']->getBody(), true));
        $this->assertNotNull($snapshot->fresh()->reverted_at);
    }

    public function test_bulk_update_keeps_queue_and_dry_run_has_no_jobs(): void
    {
        Queue::fake();
        $store = $this->store();
        $history = [];
        $this->fake([$this->product()], $history);
        app(BulkUpdateProductsAction::class)->execute($store, ['1'], ['price' => '20'], true);
        Queue::assertNothingPushed();
        app(BulkUpdateProductsAction::class)->execute($store, ['1', '2'], ['price' => '20']);
        Queue::assertPushed(UpdateProductJob::class, 2);
    }

    public function test_unsupported_fields_are_rejected_before_http(): void
    {
        $this->expectException(ValidationException::class);
        app(UpdateProductAction::class)->execute($this->store(), '1', ['raw' => ['id' => 9]]);
    }

    public function test_n1_cannot_update_products_via_livewire(): void
    {
        $user = User::factory()->create(['role' => 'n1']);
        Livewire::actingAs($user)->test(StoreProducts::class, ['store' => $this->store()])->call('queueChange', '1', 'price', '99')->assertForbidden();
    }

    public function test_drawer_preserves_filters_and_exposes_details(): void
    {
        $history = [];
        $this->fake([$this->product()], $history);
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(StoreProducts::class, ['store' => $this->store()])
            ->set('filters.sku', 'SKU-1')->set('page', 2)->call('openDetail', '1')->assertSet('page', 2)->assertSet('filters.sku', 'SKU-1')->assertSee('Produto')->assertSee('Organização')->assertSee('Mídia');
    }

    public function test_sale_product_preview_compares_regular_price(): void
    {
        $history = [];
        $this->fake([array_replace($this->product('8'), ['regular_price' => '10', 'sale_price' => '8'])], $history);
        $preview = app(UpdateProductAction::class)->execute($this->store(), '1', ['price' => '15'], true);
        $this->assertSame('10', $preview['diff']['price']['from']);
    }

    public function test_image_attributes_are_displayed_and_can_be_updated(): void
    {
        $history = [];
        $updated = $this->product();
        $updated['images'][0]['name'] = 'Frente atualizada';
        $updated['images'][0]['alt'] = 'Camiseta azul vista de frente';
        $this->fake([$this->product(), $this->product(), $updated, $updated], $history);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(StoreProducts::class, ['store' => $this->store()])
            ->call('openDetail', '1')
            ->assertSee('ALT: Frente azul')
            ->assertSee('Nome: Imagem frontal')
            ->assertSee('Editar atributos')
            ->set('draft.images.0.name', 'Frente atualizada')
            ->set('draft.images.0.alt', 'Camiseta azul vista de frente')
            ->call('saveDetail')
            ->assertHasNoErrors()
            ->assertSee('ALT: Camiseta azul vista de')
            ->assertSee('Nome: Frente atualizada');

        $payload = json_decode((string) $history[2]['request']->getBody(), true);
        $this->assertSame([[
            'name' => 'Frente atualizada',
            'alt' => 'Camiseta azul vista de frente',
            'id' => 81,
        ]], $payload['images']);
    }

    public function test_drawer_closes_and_keeps_the_error_visible_when_an_update_fails(): void
    {
        $history = [];
        $this->fake([$this->product()], $history);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(StoreProducts::class, ['store' => $this->store()])
            ->call('openDetail', '1')
            ->set('draft.images.0.id', null)
            ->set('draft.images.0.src', null)
            ->set('draft.images.0.alt', 'Texto alterado')
            ->call('saveDetail')
            ->assertSet('selectedKey', null)
            ->assertSet('detail', [])
            ->assertHasErrors('edit')
            ->assertSee('A imagem precisa de um ID ou URL válida.');
    }
}
