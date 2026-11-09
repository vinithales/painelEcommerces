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
use Illuminate\Support\Facades\File;
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

    public function test_ssh_uses_store_credentials_and_persists_success_and_script_failure(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('The SSH executor runs in the Linux application container.');
        }
        $directory = sys_get_temp_dir().'/ssh-executor-test-'.bin2hex(random_bytes(8));
        File::makeDirectory($directory, 0700);
        $oldStorage = storage_path();
        $oldPath = getenv('PATH');
        $originalServer = $_SERVER;
        $originalEnv = $_ENV;
        $environment = [
            'PATH' => $directory.':'.$oldPath,
            'SSH_TEST_CAPTURE' => $directory.'/capture.json',
            'SSH_TEST_EXIT' => '0',
        ];
        $originalEnvironment = [];
        foreach ($environment as $name => $value) {
            $originalEnvironment[$name] = getenv($name);
            putenv($name.'='.$value);
            $_SERVER[$name] = $_ENV[$name] = $value;
        }
        $this->app->useStoragePath($directory.'/storage');

        try {
            File::copy(base_path('tests/Fixtures/ssh-client.php'), $directory.'/ssh');
            chmod($directory.'/ssh', 0700);
            $assignment = $this->createAssignment(['executor_type' => 'ssh']);
            $assignment->store->update([
                'ssh_ip' => '192.0.2.10', 'ssh_user' => 'monitor', 'ssh_password' => ' test-password ',
            ]);
            $assignment->checkDefinition->update([
                'configuration' => ['script' => "printf 'indices-ok'\r\n", 'timeout' => 10],
                'assertions' => [['field' => 'exit_code', 'operator' => 'equals', 'expected' => 0]],
            ]);
            $this->assertNull($assignment->store->ssh_profile);
            $this->assertNotSame(' test-password ', $assignment->store->getRawOriginal('ssh_password'));

            // Load credentials from the database just as the worker does.
            $execution = app(CheckExecutionService::class)->run($assignment->fresh());
            $this->assertSame('completed', $execution->status, $execution->error_message ?? '');
            $this->assertSame('success', $execution->outcome);
            $this->assertSame(0, $execution->fresh()->result['observed']['exit_code']);
            $this->assertSame("indices-ok\n", $execution->result['observed']['stdout']);
            $this->assertSame("diagnostic-output\n", $execution->result['observed']['stderr']);
            $this->assertTrue($execution->result['assertions'][0]['passed']);

            $capture = json_decode(File::get($directory.'/capture.json'), true);
            $this->assertTrue($capture['password_matches']);
            $this->assertSame('force', $capture['askpass_required']);
            $this->assertSame("printf 'indices-ok'\n\n", $capture['script']);
            $arguments = $capture['arguments'];
            $this->assertSame('monitor', $arguments[array_search('-l', $arguments) + 1]);
            $this->assertSame('22', $arguments[array_search('-p', $arguments) + 1]);
            $this->assertSame(['192.0.2.10', 'sh -s'], array_slice($arguments, -2));
            $this->assertStringNotContainsString('test-password', implode(' ', $arguments));
            $this->assertStringNotContainsString('test-password', json_encode($execution->result));
            $this->assertDirectoryDoesNotExist(dirname($capture['askpass']));

            putenv('SSH_TEST_EXIT=1');
            $_SERVER['SSH_TEST_EXIT'] = $_ENV['SSH_TEST_EXIT'] = '1';
            $failed = app(CheckExecutionService::class)->run($assignment->fresh());
            $this->assertSame('completed', $failed->status);
            $this->assertSame('failed', $failed->outcome);
            $this->assertFalse($failed->result['assertions'][0]['passed']);
        } finally {
            $this->app->useStoragePath($oldStorage);
            foreach ($originalEnvironment as $name => $value) {
                putenv($value === false ? $name : $name.'='.$value);
            }
            $_SERVER = $originalServer;
            $_ENV = $originalEnv;
            File::deleteDirectory($directory);
        }
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
