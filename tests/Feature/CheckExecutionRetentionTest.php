<?php

namespace Tests\Feature;

use App\Models\CheckAssignment;
use App\Models\CheckDefinition;
use App\Models\CheckExecution;
use App\Models\Store;
use App\Services\Checks\CheckExecutionRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckExecutionRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_cleans_only_old_finished_bodies_and_is_idempotent(): void
    {
        $this->freezeTime();
        $store = Store::create(['name' => 'Retention test', 'platform' => 'magento2', 'store_url' => 'https://example.com']);
        $definition = CheckDefinition::create([
            'name' => 'Body check', 'level' => 'SITE', 'executor_type' => 'http',
            'configuration' => [], 'assertions' => [],
        ]);
        $assignment = CheckAssignment::create([
            'store_id' => $store->id, 'check_definition_id' => $definition->id, 'interval_minutes' => 5, 'enabled' => true,
        ]);
        $result = [
            'observed' => ['body' => '<html>large response</html>', 'status_code' => 200, 'stdout' => 'SSH output'],
            'assertions' => [
                ['field' => 'body', 'actual' => '<html>large response</html>', 'expected' => 'html', 'passed' => true],
                ['field' => 'status_code', 'actual' => 200, 'expected' => 200, 'passed' => true],
            ],
        ];
        $old = [];
        for ($index = 0; $index < 101; $index++) {
            $old[] = $assignment->executions()->create([
                'status' => $index % 2 === 0 ? 'completed' : 'error',
                'outcome' => $index % 2 === 0 ? 'success' : null,
                'error_message' => $index % 2 === 0 ? null : 'Connection interrupted',
                'definition_snapshot' => ['name' => 'Original check'],
                'finished_at' => now()->subDays(90)->subSecond(), 'result' => $result,
            ]);
        }
        $preserved = [];
        foreach ([
            ['completed', now()->subDays(90), $result],
            ['error', now()->subDays(90)->addSecond(), $result],
            ['running', now()->subDays(91), $result],
            ['pending', now()->subDays(91), $result],
            ['completed', null, $result],
            ['error', now()->subDays(91), null],
            ['completed', now()->subDays(91), ['observed' => ['exit_code' => 0, 'stdout' => 'ok']]],
        ] as [$status, $finishedAt, $data]) {
            $preserved[] = $assignment->executions()->create([
                'status' => $status, 'finished_at' => $finishedAt, 'result' => $data,
            ]);
        }
        $this->artisan('checks:clean-execution-results')
            ->expectsOutput('Resultados de execuções limpos: 101.')->assertSuccessful();

        $expected = $result;
        unset($expected['observed']['body'], $expected['assertions'][0]['actual']);
        foreach ($old as $execution) {
            $this->assertSame($expected, $execution->fresh()->result);
            foreach (['status', 'outcome', 'error_message', 'definition_snapshot', 'finished_at'] as $field) {
                $this->assertEquals($execution->$field, $execution->fresh()->$field);
            }
        }
        foreach ($preserved as $execution) {
            $this->assertSame($execution->result, $execution->fresh()->result);
        }
        $this->assertSame(0, app(CheckExecutionRetentionService::class)->clean());
        $this->assertDatabaseCount('check_executions', count($old) + count($preserved));
    }
}
