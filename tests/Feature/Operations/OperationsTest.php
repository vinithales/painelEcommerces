<?php

namespace Tests\Feature\Operations;

use App\Livewire\Stores\EditStore;
use App\Livewire\Stores\CheckDefinitions;
use App\Livewire\Stores\StoreDiagnostics;
use App\Models\AuditEvent;
use App\Models\Store;
use App\Models\User;
use App\Services\Checks\PublicHttpClient;
use App\Support\OperationsQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
        $this->app->bind(PublicHttpClient::class, fn () => new class extends PublicHttpClient
        {
            public function address(string $url): array
            {
                return ['example.com', 443, '93.184.216.34'];
            }
        });
    }

    private function store(): Store
    {
        return Store::create(['name' => 'Loja teste', 'platform' => 'woocommerce', 'store_url' => 'https://example.com']);
    }

    private function user(string $role = 'admin'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_visitors_are_redirected_and_n1_cannot_configure(): void
    {
        $this->get('/stores')->assertRedirect('/login');
        $store = $this->store();
        $this->actingAs($this->user('n1'))->get('/stores/'.$store->id.'/edit')->assertForbidden();
        Livewire::actingAs(auth()->user())->test(CheckDefinitions::class)->call('create')->assertForbidden();
        Livewire::actingAs(auth()->user())->test(StoreDiagnostics::class, ['store' => $store])->call('saveConnection')->assertForbidden();
    }

    public function test_admin_pages_render_without_calling_remote_apis(): void
    {
        $store = $this->store();
        $this->actingAs($this->user());
        foreach (['/stores', '/stores/checks', '/stores/'.$store->id.'/products', '/stores/'.$store->id.'/orders', '/stores/'.$store->id.'/overview', '/stores/'.$store->id.'/diagnostics', '/stores/'.$store->id.'/tools'] as $url) {
            $this->get($url)->assertOk();
        }
        Http::assertNothingSent();
    }

    public function test_public_http_rejects_private_destinations(): void
    {
        $this->expectException(\RuntimeException::class);
        (new PublicHttpClient)->address('https://127.0.0.1/admin');
    }

    public function test_redaction_is_applied_before_audit_persistence(): void
    {
        AuditEvent::record(null, 'test', 'read', ['password' => 'do-not-store', 'nested' => ['access_token' => 'secret']]);
        $parameters = AuditEvent::first()->parameters;
        $this->assertSame('[REDACTED]', $parameters['password']);
        $this->assertSame('[REDACTED]', $parameters['nested']['access_token']);
    }

    public function test_credentials_are_not_serialized_or_prefilled(): void
    {
        $store = $this->store();
        $store->wooCommerceIntegration()->create(['consumer_key' => 'ck_test', 'consumer_secret' => 'super-secret']);
        $this->assertArrayNotHasKey('consumer_secret', $store->wooCommerceIntegration->toArray());
        Livewire::actingAs($this->user())->test(EditStore::class, ['store' => $store])->assertSet('form.consumer_secret', '')->assertDontSee('super-secret');
    }

    public function test_sync_queue_is_rejected(): void
    {
        config(['operations.queue_connection' => 'sync']);
        $this->expectException(\RuntimeException::class);
        OperationsQueue::connection();
    }
}
