<?php

namespace Tests\Feature;

use App\Models\CheckDefinition;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyChecksRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_cleanup_keeps_current_checks_and_unrelated_records(): void
    {
        $definition = CheckDefinition::create([
            'name' => 'Verificação atual', 'level' => 'SITE', 'executor_type' => 'http',
            'configuration' => ['path' => '/'],
            'assertions' => [['field' => 'status_code', 'operator' => 'equals', 'expected' => 200]],
        ]);
        $tables = ['store_check_alerts', 'store_check_runs', 'store_checks', 'store_integration_status'];
        foreach ($tables as $table) {
            Schema::create($table, fn (Blueprint $blueprint) => $blueprint->id());
            DB::table($table)->insert(['id' => 1]);
        }
        foreach (['App\\Jobs\\RunCheckJob', 'App\\Jobs\\RunCheckAssignmentJob'] as $job) {
            DB::table('jobs')->insert([
                'queue' => 'default', 'payload' => json_encode(['displayName' => $job]),
                'attempts' => 0, 'available_at' => time(), 'created_at' => time(),
            ]);
            DB::table('failed_jobs')->insert([
                'uuid' => (string) \Illuminate\Support\Str::uuid(), 'connection' => 'database', 'queue' => 'default',
                'payload' => json_encode(['data' => ['commandName' => $job]]), 'exception' => 'Teste', 'failed_at' => now(),
            ]);
        }
        foreach (['check', 'support'] as $resource) {
            DB::table('audit_events')->insert(['resource' => $resource, 'action' => 'run', 'result' => 'success', 'created_at' => now(), 'updated_at' => now()]);
        }

        $migration = require database_path('migrations/2026_10_06_000001_remove_legacy_store_checks.php');
        $migration->up();
        $migration->up();

        foreach ($tables as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertDatabaseHas('check_definitions', ['id' => $definition->id, 'name' => 'Verificação atual']);
        $this->assertTrue(Schema::hasTable('check_assignments'));
        $this->assertTrue(Schema::hasTable('check_executions'));
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertStringContainsString('RunCheckAssignmentJob', DB::table('jobs')->value('payload'));
        $this->assertStringContainsString('RunCheckAssignmentJob', DB::table('failed_jobs')->value('payload'));
        $this->assertDatabaseMissing('audit_events', ['resource' => 'check']);
        $this->assertDatabaseHas('audit_events', ['resource' => 'support']);
    }
}
