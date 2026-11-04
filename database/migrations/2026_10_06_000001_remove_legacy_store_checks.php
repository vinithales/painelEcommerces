<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacyJobs = [
            'App\\Jobs\\RunCheckJob',
            'App\\Jobs\\TestCheckJob',
            'App\\Jobs\\SendCheckAlertJob',
            'App\\Jobs\\CheckStoreIntegrationJob',
        ];

        foreach (['jobs', 'failed_jobs'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            DB::table($table)->select('id', 'payload')->chunkById(100, function ($rows) use ($table, $legacyJobs) {
                $ids = [];
                foreach ($rows as $row) {
                    $payload = json_decode($row->payload, true);
                    if (in_array($payload['displayName'] ?? null, $legacyJobs, true)
                        || in_array($payload['data']['commandName'] ?? null, $legacyJobs, true)) {
                        $ids[] = $row->id;
                    }
                }
                if ($ids !== []) {
                    DB::table($table)->whereIn('id', $ids)->delete();
                }
            });
        }

        if (Schema::hasTable('audit_events')) {
            DB::table('audit_events')->where('resource', 'check')->delete();
        }

        foreach (['store_check_alerts', 'store_check_runs', 'store_checks', 'store_integration_status'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('A remoção dos dados legados é definitiva. A reversão exige restaurar um backup.');
    }
};
