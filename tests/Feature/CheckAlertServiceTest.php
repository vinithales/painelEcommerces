<?php

namespace Tests\Feature;

use App\Models\CheckAssignment;
use App\Models\CheckDefinition;
use App\Models\CheckExecution;
use App\Models\Store;
use App\Services\Checks\CheckAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_opens_an_incident_when_the_latest_finished_executions_all_fail(): void
    {
        $assignment = $this->createAssignment([
            'alert_enabled' => true,
            'failure_threshold' => 2,
        ]);
        $this->createExecution($assignment, 'error');
        $this->createExecution($assignment, 'completed', 'failed');

        app(CheckAlertService::class)->evaluate($assignment);

        $this->assertDatabaseCount('check_incidents', 1);
        $this->assertNotNull($assignment->incidents()->first()->opened_at);
    }

    public function test_it_does_not_open_an_incident_when_alerts_are_disabled(): void
    {
        $assignment = $this->createAssignment([
            'alert_enabled' => false,
            'failure_threshold' => 1,
        ]);
        $this->createExecution($assignment, 'error');

        app(CheckAlertService::class)->evaluate($assignment);

        $this->assertDatabaseCount('check_incidents', 0);
    }

    public function test_it_does_not_open_a_duplicate_incident(): void
    {
        $assignment = $this->createAssignment([
            'alert_enabled' => true,
            'failure_threshold' => 1,
        ]);
        $assignment->incidents()->create(['opened_at' => now()]);
        $this->createExecution($assignment, 'completed', 'failed');

        app(CheckAlertService::class)->evaluate($assignment);

        $this->assertDatabaseCount('check_incidents', 1);
    }

    public function test_it_does_not_count_unfinished_executions(): void
    {
        $assignment = $this->createAssignment([
            'alert_enabled' => true,
            'failure_threshold' => 1,
        ]);
        $this->createExecution($assignment, 'error', null, false);

        app(CheckAlertService::class)->evaluate($assignment);

        $this->assertDatabaseCount('check_incidents', 0);
    }

    public function test_a_recent_success_prevents_an_incident_even_when_older_executions_failed(): void
    {
        $assignment = $this->createAssignment([
            'alert_enabled' => true,
            'failure_threshold' => 2,
        ]);
        $this->createExecution($assignment, 'error');
        $this->createExecution($assignment, 'completed', 'success');

        app(CheckAlertService::class)->evaluate($assignment);

        $this->assertDatabaseCount('check_incidents', 0);
    }

    public function test_it_resolves_an_incident_when_the_recovery_threshold_is_reached(): void
    {
        $this->travelTo(now()->startOfSecond());
        $assignment = $this->createAssignment([
            'alert_enabled' => true,
            'recovery_threshold' => 2,
        ]);
        $incident = $assignment->incidents()->create(['opened_at' => now()]);
        $this->createExecution($assignment, 'completed', 'success');

        app(CheckAlertService::class)->evaluate($assignment);
        $this->assertNull($incident->fresh()->resolved_at);

        $this->createExecution($assignment, 'completed', 'success');
        $this->createExecution($assignment, 'error', null, false);

        app(CheckAlertService::class)->evaluate($assignment);

        $this->assertTrue($incident->fresh()->resolved_at->equalTo(now()));
        $this->assertDatabaseCount('check_incidents', 1);
    }

    public function test_a_failure_between_recent_successes_keeps_the_incident_open(): void
    {
        $assignment = $this->createAssignment([
            'alert_enabled' => true,
            'recovery_threshold' => 2,
        ]);
        $incident = $assignment->incidents()->create(['opened_at' => now()]);
        $this->createExecution($assignment, 'completed', 'success');
        $this->createExecution($assignment, 'completed', 'failed');
        $this->createExecution($assignment, 'completed', 'success');

        app(CheckAlertService::class)->evaluate($assignment);

        $this->assertNull($incident->fresh()->resolved_at);
        $this->assertDatabaseCount('check_incidents', 1);
    }

    private function createAssignment(array $overrides = []): CheckAssignment
    {
        $definition = CheckDefinition::create([
            'name' => 'Disponibilidade da loja',
            'level' => 'SITE',
            'executor_type' => 'http',
            'configuration' => ['url' => 'https://example.com'],
            'assertions' => [['field' => 'status_code', 'operator' => 'equals', 'expected' => 200]],
        ]);
        $store = Store::create([
            'name' => 'Loja teste',
            'platform' => Store::PLATFORM_WOOCOMMERCE,
            'store_url' => 'https://example.com',
        ]);

        return CheckAssignment::create(array_merge([
            'check_definition_id' => $definition->id,
            'store_id' => $store->id,
            'interval_minutes' => 5,
        ], $overrides));
    }

    private function createExecution(
        CheckAssignment $assignment,
        string $status,
        ?string $outcome = null,
        bool $finished = true,
    ): CheckExecution {
        return $assignment->executions()->create([
            'status' => $status,
            'outcome' => $outcome,
            'finished_at' => $finished ? now() : null,
        ]);
    }
}
