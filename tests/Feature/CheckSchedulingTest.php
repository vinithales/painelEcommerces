<?php

namespace Tests\Feature;

use App\Jobs\RunCheckAssignmentJob;
use App\Models\CheckAssignment;
use App\Models\CheckDefinition;
use App\Models\CheckExecution;
use App\Models\Store;
use App\Services\Checks\CheckExecutionService;
use App\Services\Checks\CheckSchedulingService;
use App\Services\Checks\Executors\HttpExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CheckSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_due_persists_a_job_and_runs_its_original_snapshot(): void
    {
        $this->travelTo(now()->startOfSecond());
        // The test default is sync: this must explicitly enqueue on database.
        $assignment = $this->assignment(now()->subMinute());
        $snapshot = $assignment->checkDefinition->only(['name', 'level', 'executor_type', 'configuration', 'assertions']);

        app(CheckSchedulingService::class)->dispatchDue();

        $execution = CheckExecution::sole();
        $this->assertSame('pending', $execution->status);
        $this->assertSame($snapshot, $execution->definition_snapshot);
        $this->assertTrue($assignment->fresh()->next_run_at->equalTo(now()->addMinutes(5)));
        $this->assertDatabaseCount('jobs', 1);
        $payload = json_decode(DB::table('jobs')->sole()->payload, true);
        $job = unserialize($payload['data']['command']);
        $this->assertInstanceOf(RunCheckAssignmentJob::class, $job);
        $this->assertSame($assignment->id, $job->assignmentId);
        $this->assertSame($execution->id, $job->executionId);
        $this->assertSame('database', $job->connection);

        app(CheckSchedulingService::class)->dispatchDue();
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('check_executions', 1);

        $assignment->checkDefinition->update(['configuration' => ['path' => '/changed']]);
        $http = \Mockery::mock(HttpExecutor::class);
        $http->shouldReceive('execute')->once()->with('https://example.com', $snapshot['configuration'])
            ->andReturn(['status_code' => 200]);
        $this->instance(HttpExecutor::class, $http);
        $job->handle(app(CheckExecutionService::class));
        $this->assertSame('success', $execution->fresh()->outcome);
        $this->assertSame('completed', $execution->fresh()->status);
    }

    public function test_command_only_dispatches_due_enabled_assignments_across_chunks(): void
    {
        $this->travelTo(now()->startOfSecond());
        Queue::fake();
        $disabled = $this->assignment(now()->subDay(), false);
        $future = $this->assignment(now()->addSecond());
        $unscheduled = $this->assignment(null);
        for ($index = 0; $index < 101; $index++) {
            $this->assignment(now());
        }

        $this->artisan('checks:dispatch-due')->assertSuccessful();
        Queue::assertPushed(RunCheckAssignmentJob::class, 101);
        $this->assertDatabaseCount('check_executions', 101);
        foreach ([$disabled, $future, $unscheduled] as $assignment) {
            $this->assertSame(0, $assignment->executions()->count());
            $this->assertEquals($assignment->next_run_at, $assignment->fresh()->next_run_at);
        }
    }

    private function assignment($due, bool $enabled = true): CheckAssignment
    {
        $store = Store::firstOrCreate(['store_url' => 'https://example.com'], [
            'name' => 'Scheduling test', 'platform' => 'magento2',
        ]);
        $definition = CheckDefinition::create([
            'name' => 'Scheduled check', 'level' => 'SITE', 'executor_type' => 'http',
            'configuration' => ['path' => '/', 'method' => 'GET', 'timeout' => 10, 'follow_redirects' => false],
            'assertions' => [['field' => 'status_code', 'operator' => 'equals', 'expected' => 200]],
        ]);

        return CheckAssignment::create([
            'store_id' => $store->id, 'check_definition_id' => $definition->id,
            'interval_minutes' => 5, 'enabled' => $enabled, 'next_run_at' => $due,
        ]);
    }
}
