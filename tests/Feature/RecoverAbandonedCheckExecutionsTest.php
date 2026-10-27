<?php

namespace Tests\Feature;

use App\Jobs\RunCheckAssignmentJob;
use App\Jobs\WarmProductsCacheJob;
use App\Models\CheckAssignment;
use App\Models\CheckDefinition;
use App\Models\CheckExecution;
use App\Models\Store;
use App\Services\Checks\RecoverAbandonedCheckExecutionsService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecoverAbandonedCheckExecutionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_recovers_only_abandoned_running_executions_and_preserves_history(): void
    {
        $this->freezeTime();
        $assignment = $this->assignment();
        $abandoned = $this->execution($assignment, ['started_at' => now()->subMinutes(6)]);
        $original = $abandoned->getAttributes();

        $untouched = [
            $this->execution($assignment, ['started_at' => now()->subMinutes(5)]),
            $this->execution($assignment, ['started_at' => now()->subSeconds(299)]),
            $this->execution($assignment, ['started_at' => null]),
            $this->execution($assignment, ['status' => 'pending', 'started_at' => null, 'created_at' => now()->subDays(7)]),
            $this->execution($assignment, ['status' => 'pending', 'started_at' => now()->subDays(7)]),
            $this->execution($assignment, ['status' => 'completed', 'started_at' => now()->subHour()]),
            $this->execution($assignment, ['status' => 'error', 'started_at' => now()->subHour(), 'error_message' => 'Erro original']),
            $this->execution($assignment, ['started_at' => now()->subHour(), 'finished_at' => now()->subMinutes(50)]),
        ];
        $before = array_map(fn ($execution) => $execution->getAttributes(), $untouched);

        $service = app(RecoverAbandonedCheckExecutionsService::class);
        $this->assertSame(1, $service->recover());
        $abandoned->refresh();
        $this->assertSame('error', $abandoned->status);
        $this->assertNull($abandoned->outcome);
        $this->assertSame(now()->toDateTimeString(), $abandoned->finished_at->toDateTimeString());
        $this->assertStringContainsString('mais de 5 minutos', $abandoned->error_message);
        foreach (['id', 'check_assignment_id', 'definition_snapshot', 'result', 'started_at', 'created_at'] as $field) {
            $this->assertSame($original[$field], $abandoned->getAttributes()[$field]);
        }
        foreach ($untouched as $index => $execution) {
            $this->assertSame($before[$index], $execution->refresh()->getAttributes());
        }

        $this->travel(1)->minutes();
        // Exactly five minutes becomes eligible only after time advances.
        $this->assertSame(2, $service->recover());
        $recovered = $abandoned->getAttributes();
        $this->assertSame(0, $service->recover());
        $this->assertSame($recovered, $abandoned->refresh()->getAttributes());
        $this->assertDatabaseCount('check_executions', 9);
    }

    public function test_command_reports_recovery_and_is_scheduled_every_minute(): void
    {
        $this->freezeTime();
        $execution = $this->execution($this->assignment(), ['started_at' => now()->subSeconds(301)]);

        $this->artisan('checks:recover-abandoned')
            ->expectsOutput('Execuções recuperadas: 1.')
            ->assertSuccessful();
        $this->assertSame('error', $execution->refresh()->status);
        $this->artisan('checks:recover-abandoned')
            ->expectsOutput('Execuções recuperadas: 0.')
            ->assertSuccessful();

        $event = collect(app(Schedule::class)->events())->first(
            fn ($event) => str_contains($event->command ?? '', 'checks:recover-abandoned')
        );
        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_queue_reservation_exceeds_job_timeouts_and_check_timeout_precedes_recovery(): void
    {
        $checkJob = new RunCheckAssignmentJob(1);
        $warmJob = new WarmProductsCacheJob(new Store);

        $this->assertLessThan(300, $checkJob->timeout);
        foreach (['database', 'redis', 'beanstalkd'] as $connection) {
            $retryAfter = config("queue.connections.{$connection}.retry_after");
            $this->assertGreaterThan($checkJob->timeout, $retryAfter);
            $this->assertGreaterThan($warmJob->timeout, $retryAfter);
        }
    }

    private function execution(CheckAssignment $assignment, array $overrides): CheckExecution
    {
        return $assignment->executions()->create(array_replace([
            'definition_snapshot' => $assignment->checkDefinition->only(['name', 'level', 'executor_type', 'configuration', 'assertions']),
            'status' => 'running',
            'result' => ['partial' => 'preserved'],
            'outcome' => null,
        ], $overrides))->refresh();
    }

    private function assignment(): CheckAssignment
    {
        $definition = CheckDefinition::create([
            'name' => 'Disponibilidade', 'level' => 'SITE', 'executor_type' => 'http',
            'configuration' => ['path' => '/', 'method' => 'GET', 'timeout' => 10, 'follow_redirects' => false],
            'assertions' => [['field' => 'status_code', 'operator' => 'equals', 'expected' => 200]],
        ]);
        $store = Store::create([
            'name' => 'Loja teste', 'platform' => Store::PLATFORM_WOOCOMMERCE, 'store_url' => 'https://example.com',
        ]);

        return CheckAssignment::create([
            'check_definition_id' => $definition->id, 'store_id' => $store->id,
            'enabled' => true, 'interval_minutes' => 5,
        ]);
    }
}
