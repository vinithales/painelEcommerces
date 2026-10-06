<?php

namespace Tests\Feature\Operations;

use App\Jobs\RunCheckJob;
use App\Jobs\SendCheckAlertJob;
use App\Livewire\Stores\EditStore;
use App\Livewire\Stores\CheckDefinitions;
use App\Livewire\Stores\StoreDiagnostics;
use App\Mail\OperationalCheckAlert;
use App\Models\AuditEvent;
use App\Models\Store;
use App\Models\StoreCheck;
use App\Models\User;
use App\Services\Checks\CheckConfiguration;
use App\Services\Checks\CheckDispatcher;
use App\Services\Checks\CheckRegistry;
use App\Services\Checks\CheckRunner;
use App\Services\Checks\PublicHttpClient;
use App\Services\PlatformApi\Contracts\PlatformApiAdapter;
use App\Services\PlatformApi\PlatformApiAdapterFactory;
use App\Support\OperationsQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
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

    private function check(string $type = 'HTTP_STATUS', array $extra = []): StoreCheck
    {
        return StoreCheck::create($extra + ['store_id' => $this->store()->id, 'name' => 'Check teste', 'level' => 'SITE', 'check_type' => $type, 'enabled' => true, 'severity' => 'CRITICAL', 'configuration' => CheckConfiguration::defaults($type), 'interval_seconds' => 300, 'next_run_at' => now(), 'consecutive_failures_before_alert' => 2, 'failure_count' => 0, 'last_status' => 'OK', 'cooldown_seconds' => 1800, 'notify_on_failure' => true, 'notify_on_recovery' => true])->fresh();
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

    public function test_scheduler_claims_only_due_enabled_checks_once(): void
    {
        $due = $this->check();
        $this->check(extra: ['next_run_at' => now()->addHour()]);
        $this->check(extra: ['enabled' => false]);
        $dispatcher = app(CheckDispatcher::class);
        $this->assertSame(1, $dispatcher->due());
        $this->assertSame(0, $dispatcher->due());
        Queue::assertPushed(RunCheckJob::class, 1);
        Queue::assertPushed(RunCheckJob::class, fn ($job) => $job->checkId === $due->id);
    }

    public function test_disabled_check_never_executes(): void
    {
        $check = $this->check(extra: ['enabled' => false]);
        $this->assertNull(app(CheckRunner::class)->run($check));
        Http::assertNothingSent();
    }

    public function test_threshold_recovery_and_notification_do_not_storm(): void
    {
        Http::fakeSequence()->push('', 500)->push('', 500)->push('', 500)->push('', 200);
        $check = $this->check();
        $runner = app(CheckRunner::class);
        $runner->run($check);
        $this->assertDatabaseCount('store_check_alerts', 0);
        $runner->run($check->fresh());
        $this->assertDatabaseCount('store_check_alerts', 1);
        $runner->run($check->fresh());
        $this->assertDatabaseCount('store_check_alerts', 1);
        $runner->run($check->fresh());
        $this->assertDatabaseCount('store_check_alerts', 2);
        $this->assertSame(0, $check->fresh()->failure_count);
        $this->assertNull($check->fresh()->alert_status);
        $this->assertDatabaseHas('store_check_alerts', ['to_status' => 'OK']);
    }

    public function test_unknown_is_execution_failure_not_target_recovery(): void
    {
        $check = $this->check(extra: ['check_type' => 'UNSUPPORTED', 'alert_status' => 'CRITICAL', 'failure_count' => 3]);
        $r = app(CheckRunner::class)->run($check);
        $this->assertSame('UNKNOWN', $r->status);
        $this->assertSame('CRITICAL', $check->fresh()->alert_status);
        $this->assertDatabaseCount('store_check_alerts', 0);
    }

    public function test_timeout_is_target_failure(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $r = app(CheckRegistry::class)->evaluate($this->check());
        $this->assertSame('CRITICAL', $r->status);
    }

    public function test_each_site_strategy(): void
    {
        $cases = [
            ['HTTP_STATUS', '', 200, [], 'OK'],
            ['HTTP_STATUS', '', 404, [], 'CRITICAL'],
            ['ROBOTS_TXT', "User-agent: *\nDisallow: /", 200, [], 'CRITICAL'],
            ['ROBOTS_TXT', "User-agent: *\nDisallow: /admin\nSitemap: https://example.com/sitemap.xml", 200, [], 'OK'],
            ['ROBOTS_TXT', '<html>Error</html>', 200, [], 'CRITICAL'],
            ['SITEMAP_XML', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com</loc></url></urlset>', 200, [], 'OK'],
            ['SITEMAP_XML', '<html/>', 200, [], 'CRITICAL'],
            ['SITEMAP_XML', '<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><urlset/>', 200, [], 'CRITICAL'],
            ['META_ROBOTS', '<meta content="index,follow" name="robots">', 200, [], 'OK'],
            ['META_ROBOTS', '<meta name="robots" content="noindex,follow">', 200, [], 'CRITICAL'],
            ['META_ROBOTS', '<html/>', 200, [], 'CRITICAL'],
            ['META_ROBOTS', '<meta name="robots" content="index,follow">', 200, ['X-Robots-Tag' => 'noindex'], 'CRITICAL'],
            ['MAINTENANCE', '<h1>Site em manutenção</h1>', 200, [], 'CRITICAL'],
            ['MAINTENANCE', '<h1>Loja aberta</h1>', 200, [], 'OK'],
            ['CONTENT', 'Bem-vindo', 200, [], 'OK'],
        ];
        foreach ($cases as [$type,$body,$code,$headers,$expected]) {
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::fake(['*' => Http::response($body, $code, $headers)]);
            $check = $this->check($type);
            if ($type === 'CONTENT') {
                $check->configuration = array_replace($check->configuration, ['text' => 'Bem-vindo']);
            }
            $this->assertSame($expected, app(CheckRegistry::class)->evaluate($check)->status, $type.' '.$body);
        }
    }

    public function test_configuration_rejects_arbitrary_json_and_invalid_regex(): void
    {
        $this->expectException(ValidationException::class);
        app(CheckConfiguration::class)->validate('HTTP_STATUS', CheckConfiguration::defaults('HTTP_STATUS') + ['command' => 'rm']);
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

    public function test_alert_email_uses_configured_mailer(): void
    {
        Mail::fake();
        config(['operations.recipients' => ['test@example.com']]);
        $check = $this->check();
        $alert = $check->alerts()->create(['from_status' => 'OK', 'to_status' => 'CRITICAL']);
        (new SendCheckAlertJob($alert->id))->handle();
        Mail::assertSent(OperationalCheckAlert::class);
        $this->assertNotNull($alert->fresh()->sent_at);
    }

    public function test_manual_execution_is_audited(): void
    {
        $this->actingAs($this->user('n1'));
        app(CheckDispatcher::class)->dispatch($this->check(), true, auth()->id());
        $this->assertDatabaseHas('audit_events', ['user_id' => auth()->id(), 'action' => 'run']);
    }

    public function test_expired_queue_claim_cannot_run_after_a_new_claim(): void
    {
        $check = $this->check();
        $dispatcher = app(CheckDispatcher::class);
        $dispatcher->dispatch($check);
        $old = $check->fresh()->claim_token;
        $check->update(['claimed_until' => now()->subMinute()]);
        $dispatcher->dispatch($check->fresh());
        (new RunCheckJob($check->id, false, $old))->handle(app(CheckRunner::class));
        Http::assertNothingSent();
        $this->assertDatabaseCount('store_check_runs', 0);
    }

    public function test_api_health_uses_read_only_adapter_operation(): void
    {
        $adapter = \Mockery::mock(PlatformApiAdapter::class);
        $adapter->shouldReceive('listProducts')->once()->with([], 1, 1)->andReturn(['data' => [], 'total' => 0]);
        $this->mock(PlatformApiAdapterFactory::class, fn ($mock) => $mock->shouldReceive('make')->andReturn($adapter));
        $this->assertSame('OK', app(CheckRegistry::class)->evaluate($this->check('API_HEALTH'))->status);
    }

    public function test_latency_uses_warning_and_critical_thresholds(): void
    {
        $this->mock(PublicHttpClient::class, function ($mock) {
            $mock->shouldReceive('resolve')->andReturn('https://example.com');
            $mock->shouldReceive('fetch')->andReturn(['status' => 200, 'duration_ms' => 3000, 'url' => 'https://example.com', 'body' => '', 'headers' => []]);
        });
        $this->assertSame('WARNING', app(CheckRegistry::class)->evaluate($this->check('RESPONSE_TIME'))->status);
    }

    public function test_invalid_regex_and_sync_queue_are_rejected(): void
    {
        try {
            app(CheckConfiguration::class)->validate('CONTENT', ['url' => '/', 'text' => 'invalid', 'regex' => true, 'mode' => 'present']);
            $this->fail('Expected invalid regex');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        config(['operations.queue_connection' => 'sync']);
        $this->expectException(\RuntimeException::class);
        OperationsQueue::connection();
    }

    public function test_alert_cooldown_and_severity_escalation(): void
    {
        $check = $this->check('RESPONSE_TIME', ['last_alert_at' => now(), 'alert_status' => 'WARNING', 'last_status' => 'WARNING', 'failure_count' => 2]);
        $this->mock(PublicHttpClient::class, function ($mock) {
            $mock->shouldReceive('resolve')->andReturn('https://example.com');
            $mock->shouldReceive('fetch')->andReturn(['status' => 200, 'duration_ms' => 6000, 'url' => 'https://example.com', 'body' => '', 'headers' => []]);
        });
        app(CheckRunner::class)->run($check);
        $this->assertDatabaseHas('store_check_alerts', ['from_status' => 'WARNING', 'to_status' => 'CRITICAL']);
    }
}
