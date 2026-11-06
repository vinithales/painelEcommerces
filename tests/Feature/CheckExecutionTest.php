<?php

namespace Tests\Feature;

use App\Models\CheckAssignment;
use App\Models\CheckDefinition;
use App\Models\CheckExecution;
use App\Models\Store;
use App\Services\Checks\AssertionEvaluator;
use App\Services\Checks\CheckExecutionService;
use App\Services\Checks\Executors\HttpExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_completed_execution_when_http_check_passes(): void
    {
        $assignment = $this->createAssignment();
        $expectedResult = [
            'status_code' => 200,
            'response_time' => 145,
            'url' => 'https://example.com/',
            'body' => 'ok',
        ];

        $httpExecutor = \Mockery::mock(HttpExecutor::class);
        $httpExecutor->shouldReceive('execute')->once()->andReturn($expectedResult);
        $this->instance(HttpExecutor::class, $httpExecutor);

        $assertionEvaluator = \Mockery::mock(AssertionEvaluator::class);
        $assertionEvaluator->shouldReceive('evaluate')->once()->withArgs(function (array $result, array $assertion) {
            $this->assertSame('status_code', $assertion['field']);
            $this->assertSame(200, $assertion['expected']);
            $this->assertSame(200, $result['status_code']);

            return true;
        })->andReturn([
            'field' => 'status_code',
            'operator' => 'equals',
            'expected' => 200,
            'actual' => 200,
            'passed' => true,
        ]);
        $this->instance(AssertionEvaluator::class, $assertionEvaluator);

        $execution = app(CheckExecutionService::class)->run($assignment);

        $this->assertInstanceOf(CheckExecution::class, $execution);
        $this->assertSame('completed', $execution->status);
        $this->assertSame('success', $execution->outcome);
        $this->assertSame($expectedResult, $execution->result['observed']);
        $this->assertSame(1, count($execution->result['assertions']));
        $this->assertTrue($execution->result['assertions'][0]['passed']);
        $this->assertNotNull($execution->finished_at);
        $this->assertDatabaseHas('check_executions', ['id' => $execution->id, 'status' => 'completed']);
    }

    public function test_api_posts_json_and_checks_a_numeric_field_in_the_response(): void
    {
        $assignment = $this->createAssignment(['executor_type' => 'api']);
        $assignment->store->update(['store_url' => 'https://93.184.216.34']);
        $assignment->checkDefinition->update([
            'configuration' => ['path' => '/health', 'timeout' => 10, 'method' => 'POST', 'payload' => '{"probe":true}', 'json_path' => 'queue.depth', 'header_lines' => 'Accept-Language: pt-BR'],
            'assertions' => [['field' => 'json_number', 'operator' => 'less_than', 'expected' => 10]],
        ]);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['queue' => ['depth' => 4]])]);
        $execution = app(CheckExecutionService::class)->run($assignment);
        $this->assertSame('success', $execution->outcome, $execution->error_message ?? '');
        $this->assertSame('4', $execution->result['observed']['body']);
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->hasHeader('accept-language', 'pt-BR') && $request->url() === 'https://93.184.216.34/health' && $request->body() === '{"probe":true}');
    }

    public function test_ssh_without_a_store_connection_records_an_actionable_error(): void
    {
        $execution = app(CheckExecutionService::class)->run($this->createAssignment(['executor_type' => 'ssh']));
        $this->assertSame('error', $execution->status);
        $this->assertStringContainsString('conexão SSH', $execution->error_message);
    }

    public function test_it_rejects_unsupported_executor_before_creating_execution(): void
    {
        $assignment = $this->createAssignment(['executor_type' => 'smtp']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Executor type não suportado: smtp');

        app(CheckExecutionService::class)->run($assignment);

        $this->assertDatabaseCount('check_executions', 0);
    }

    public function test_it_marks_execution_as_error_when_http_executor_throws(): void
    {
        $assignment = $this->createAssignment();

        $httpExecutor = \Mockery::mock(HttpExecutor::class);
        $httpExecutor->shouldReceive('execute')->once()->andThrow(new \RuntimeException('HTTP falhou'));
        $this->instance(HttpExecutor::class, $httpExecutor);

        $assertionEvaluator = \Mockery::mock(AssertionEvaluator::class);
        $assertionEvaluator->shouldReceive('evaluate')->never();
        $this->instance(AssertionEvaluator::class, $assertionEvaluator);

        $execution = app(CheckExecutionService::class)->run($assignment);

        $this->assertNotNull($execution);
        $this->assertSame('error', $execution->status);
        $this->assertNull($execution->outcome);
        $this->assertSame('HTTP falhou', $execution->error_message);
        $this->assertNotNull($execution->finished_at);
    }

    public function test_it_does_not_create_an_execution_for_disabled_or_missing_assignments(): void
    {
        $this->assertNull(app(CheckExecutionService::class)->run(null));
        $this->assertDatabaseCount('check_executions', 0);

        $assignment = $this->createAssignment();
        $assignment->update(['enabled' => false]);

        $this->assertNull(app(CheckExecutionService::class)->run($assignment));
        $this->assertDatabaseCount('check_executions', 0);
    }

    private function createAssignment(array $overrides = []): CheckAssignment
    {
        $definition = CheckDefinition::create([
            'name' => 'Disponibilidade da loja',
            'level' => 'SITE',
            'executor_type' => 'http',
            'configuration' => ['path' => '/', 'method' => 'GET', 'timeout' => 10, 'follow_redirects' => false],
            'assertions' => [[
                'field' => 'status_code',
                'operator' => 'equals',
                'expected' => 200,
            ]],
        ]);

        if (isset($overrides['executor_type'])) {
            $definition->update(['executor_type' => $overrides['executor_type']]);
        }

        $store = Store::create([
            'name' => 'Loja teste',
            'platform' => Store::PLATFORM_WOOCOMMERCE,
            'store_url' => 'https://example.com',
        ]);

        return CheckAssignment::create([
            'check_definition_id' => $definition->id,
            'store_id' => $store->id,
            'interval_minutes' => 5,
            'enabled' => true,
        ]);
    }
}
