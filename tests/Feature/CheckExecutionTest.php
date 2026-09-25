<?php

namespace Tests\Feature;

use App\Models\CheckAssignment;
use App\Models\CheckDefinition;
use App\Models\CheckExecution;
use App\Models\Store;
use App\Services\Checks\CheckExecutionService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_an_assignment_and_casts_its_result_and_dates(): void
    {
        $assignment = $this->createAssignment();
        $startedAt = now()->subSecond()->startOfSecond();
        $finishedAt = now()->startOfSecond();
        $result = [
            'status_code' => 200,
            'duration_ms' => 124,
        ];

        $execution = CheckExecution::create([
            'check_assignment_id' => $assignment->id,
            'status' => 'completed',
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'outcome' => 'passed',
            'result' => $result,
        ])->fresh();

        $this->assertTrue($execution->checkAssignment->is($assignment));
        $this->assertSame($result, $execution->result);
        $this->assertInstanceOf(CarbonInterface::class, $execution->started_at);
        $this->assertInstanceOf(CarbonInterface::class, $execution->finished_at);
        $this->assertTrue($execution->started_at->equalTo($startedAt));
        $this->assertTrue($execution->finished_at->equalTo($finishedAt));
    }

    public function test_it_creates_a_running_execution_for_an_existing_store(): void
    {
        $assignment = $this->createAssignment();

        $execution = app(CheckExecutionService::class)->run($assignment);

        $this->assertInstanceOf(CheckExecution::class, $execution);
        $this->assertSame('running', $execution->status);
        $this->assertNotNull($execution->started_at);
        $this->assertSame([
            'name' => 'Disponibilidade da loja',
            'level' => 'SITE',
            'executor_type' => 'http',
            'configuration' => ['url' => 'https://example.com'],
            'assertions' => ['status_code' => 200],
        ], $execution->definition_snapshot);
        $this->assertDatabaseHas('check_executions', [
            'id' => $execution->id,
            'check_assignment_id' => $assignment->id,
        ]);
    }

    public function test_it_does_not_create_an_execution_without_an_assignment(): void
    {
        $this->assertNull(app(CheckExecutionService::class)->run(null));
        $this->assertDatabaseCount('check_executions', 0);
    }

    private function createAssignment(): CheckAssignment
    {
        $definition = CheckDefinition::create([
            'name' => 'Disponibilidade da loja',
            'level' => 'SITE',
            'executor_type' => 'http',
            'configuration' => ['url' => 'https://example.com'],
            'assertions' => ['status_code' => 200],
        ]);
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
