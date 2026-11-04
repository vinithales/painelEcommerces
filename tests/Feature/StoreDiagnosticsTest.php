<?php

namespace Tests\Feature;

use App\Actions\Checks\CheckDefinitionValidator;
use App\Jobs\RunCheckAssignmentJob;
use App\Livewire\Stores\CheckDefinitions;
use App\Models\CheckAssignment;
use App\Models\CheckDefinition;
use App\Models\Store;
use App\Models\User;
use App\Services\Checks\AssertionEvaluator;
use App\Services\Checks\CheckExecutionService;
use App\Services\Checks\Executors\HttpExecutor;
use App\Services\Checks\PublicHttpClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class StoreDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_installer_is_idempotent_and_assigns_both_checks_to_every_store(): void
    {
        $this->store();
        $this->artisan('checks:install-store-diagnostics')->assertSuccessful();
        $this->artisan('checks:install-store-diagnostics')->assertSuccessful();
        $this->assertDatabaseCount('check_definitions', 2);
        $this->assertDatabaseCount('check_assignments', 2);
        foreach (CheckDefinition::all() as $definition) {
            app(CheckDefinitionValidator::class)->validate($definition->toArray());
        }
    }

    public function test_cache_warms_up_and_persists_each_sample_without_cookies(): void
    {
        $client = \Mockery::mock(PublicHttpClient::class);
        $client->shouldReceive('resolve')->andReturn('https://example.com/');
        foreach (['MISS', 'HIT', 'HIT'] as $cache) {
            $client->shouldReceive('fetch')->once()->ordered()->andReturn([
                'status' => 200, 'duration_ms' => 10, 'url' => 'https://example.com/', 'body' => 'ok',
                'headers' => ['X-Cache' => [$cache], 'Set-Cookie' => ['secret']],
            ]);
        }
        $result = (new HttpExecutor($client))->execute('https://example.com', [
            'method' => 'GET', 'timeout' => 10, 'follow_redirects' => true, 'samples' => 3,
        ]);
        $this->assertSame(['MISS', 'HIT', 'HIT'], array_column(array_column($result['samples'], 'headers'), 'x-cache'));
        $this->assertArrayNotHasKey('set-cookie', $result['headers']);
        $evaluator = app(AssertionEvaluator::class);
        $assertion = ['field' => 'headers.x-cache', 'operator' => 'contains_ignore_case', 'expected' => 'hit'];
        $this->assertTrue($evaluator->evaluate($result, $assertion)['passed']);
        $this->assertFalse($evaluator->evaluate(['headers' => []], $assertion)['passed']);
    }

    public function test_browser_errors_are_saved_per_store_and_fail_the_check(): void
    {
        $this->store();
        $this->artisan('checks:install-store-diagnostics')->assertSuccessful();
        $client = \Mockery::mock(PublicHttpClient::class);
        $client->shouldReceive('resolve')->andReturn('https://example.com/');
        $client->shouldReceive('address')->once()->andReturn(['example.com', 443, '93.184.216.34']);
        $this->instance(PublicHttpClient::class, $client);
        Http::fake(['browser:3000/*' => Http::response([
            'status_code' => 200, 'response_time' => 100, 'url' => 'https://example.com/',
            'console_error_count' => 1, 'page_error_count' => 1,
            'console_errors' => [['message' => 'Falha no widget', 'url' => 'https://example.com/widget.js', 'lineNumber' => 5]],
            'page_errors' => [['message' => 'Uncaught TypeError', 'stack' => 'widget.js:5']],
        ])]);
        $assignment = CheckAssignment::whereHas('checkDefinition', fn ($query) => $query->where('executor_type', 'browser'))->firstOrFail();
        $execution = app(CheckExecutionService::class)->run($assignment);
        $this->assertSame('completed', $execution->status);
        $this->assertSame('failed', $execution->outcome);
        $this->assertSame('Falha no widget', $execution->fresh()->result['observed']['console_errors'][0]['message']);
        $this->assertSame('widget.js:5', $execution->result['observed']['page_errors'][0]['stack']);
        $this->assertSame($assignment->id, $execution->check_assignment_id);
    }

    public function test_http_cannot_claim_to_observe_console_errors(): void
    {
        $this->store();
        $this->artisan('checks:install-store-diagnostics')->assertSuccessful();
        $definition = CheckDefinition::where('executor_type', 'http')->firstOrFail()->toArray();
        $definition['assertions'] = [['field' => 'console_error_count', 'operator' => 'equals', 'expected' => 0]];
        $this->expectException(ValidationException::class);
        app(CheckDefinitionValidator::class)->validate($definition);
    }

    public function test_cache_detection_handles_magento_misses_and_missing_evidence(): void
    {
        foreach ([
            [['X-Magento-Cache-Debug' => ['HIT']], 'HIT', 'x-magento-cache-debug'],
            [['X-Cache' => ['MISS, HIT']], 'MISS', 'x-cache'],
            [['CF-Cache-Status' => ['hit']], 'HIT', 'cf-cache-status'],
            [['X-LiteSpeed-Cache' => ['bypass']], 'UNKNOWN', 'x-litespeed-cache'],
            [[], 'UNKNOWN', null],
        ] as [$headers, $expected, $source]) {
            $client = \Mockery::mock(PublicHttpClient::class);
            $client->shouldReceive('resolve')->andReturn('https://example.com/');
            $client->shouldReceive('fetch')->once()->andReturn([
                'status' => 200, 'duration_ms' => 10, 'url' => 'https://example.com/', 'body' => '', 'headers' => $headers,
            ]);
            $result = (new HttpExecutor($client))->execute('https://example.com', ['method' => 'GET', 'timeout' => 10, 'follow_redirects' => true]);
            $this->assertSame($expected, $result['cache_status']);
            $this->assertSame($source, $result['cache_header']);
        }
    }

    public function test_browser_can_be_edited_and_run_through_the_panel(): void
    {
        $this->store();
        $this->artisan('checks:install-store-diagnostics')->assertSuccessful();
        $definition = CheckDefinition::where('executor_type', 'browser')->firstOrFail();
        Queue::fake();
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(CheckDefinitions::class)
            ->call('edit', $definition->id)
            ->set('browserConfiguration.wait_ms', '6000')
            ->call('save')->assertHasNoErrors()
            ->call('openExecution', $definition->id)
            ->assertSee('Executar nesta loja')
            ->call('executeSelected')->assertHasNoErrors();
        $this->assertSame(6000, $definition->fresh()->configuration['wait_ms']);
        Queue::assertPushed(RunCheckAssignmentJob::class, 1);
    }

    public function test_navigation_failure_preserves_already_observed_console_messages(): void
    {
        $this->store();
        $this->artisan('checks:install-store-diagnostics')->assertSuccessful();
        $client = \Mockery::mock(PublicHttpClient::class);
        $client->shouldReceive('resolve')->andReturn('https://example.com/');
        $client->shouldReceive('address')->andReturn(['example.com', 443, '93.184.216.34']);
        $this->instance(PublicHttpClient::class, $client);
        Http::fake(['browser:3000/*' => Http::response([
            'observation_error' => 'Navigation timeout',
            'console_errors' => [['message' => 'Erro antes do timeout']],
        ])]);
        $assignment = CheckAssignment::whereHas('checkDefinition', fn ($query) => $query->where('executor_type', 'browser'))->firstOrFail();
        $execution = app(CheckExecutionService::class)->run($assignment);
        $this->assertSame('error', $execution->status);
        $this->assertNull($execution->outcome);
        $this->assertSame('Erro antes do timeout', $execution->fresh()->result['observed']['console_errors'][0]['message']);
    }

    private function store(): Store
    {
        return Store::create(['name' => 'Loja', 'platform' => Store::PLATFORM_WOOCOMMERCE, 'store_url' => 'https://example.com']);
    }
}
