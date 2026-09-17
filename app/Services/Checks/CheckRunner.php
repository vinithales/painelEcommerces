<?php

namespace App\Services\Checks;

use App\Jobs\SendCheckAlertJob;
use App\Models\StoreCheck;
use App\Models\StoreCheckAlert;
use App\Models\StoreCheckRun;
use App\Support\Redactor;
use Illuminate\Support\Facades\DB;

class CheckRunner
{
    public function __construct(private readonly CheckRegistry $registry) {}

    public function run(StoreCheck $check, bool $manual = false): ?StoreCheckRun
    {
        if (! $check->fresh()?->enabled) {
            return null;
        }
        $started = now();
        $start = microtime(true);
        $result = $this->registry->evaluate($check);

        return DB::transaction(function () use ($check, $manual, $started, $start, $result) {
            $check = StoreCheck::lockForUpdate()->findOrFail($check->id);
            $run = $check->runs()->create(['status' => $result->status, 'summary' => $result->summary, 'details' => Redactor::clean($result->details), 'metrics' => $result->metrics, 'started_at' => $started, 'finished_at' => now(), 'duration_ms' => (int) round((microtime(true) - $start) * 1000), 'manual' => $manual, 'error_message' => Redactor::clean($result->error)]);
            $previous = $check->last_status;
            $failure = in_array($result->status, ['WARNING', 'CRITICAL'], true);
            $check->failure_count = $failure ? $check->failure_count + 1 : 0;
            $check->last_status = $result->status;
            $check->last_run_at = now();
            $check->next_run_at = now()->addSeconds($check->interval_seconds);
            $check->claimed_until = null;
            $recovered = $result->status === 'OK' && $check->alert_status !== null;
            $escalated = $check->alert_status === 'WARNING' && $result->status === 'CRITICAL';
            $cool = $check->last_alert_at === null || $check->last_alert_at->lte(now()->subSeconds($check->cooldown_seconds));
            $sendFailure = $failure && $check->failure_count >= $check->consecutive_failures_before_alert && ($check->alert_status === null || $escalated) && ($cool || $escalated);
            if (($sendFailure && $check->notify_on_failure) || ($recovered && $check->notify_on_recovery)) {
                $alert = StoreCheckAlert::create(['store_check_id' => $check->id, 'from_status' => $check->alert_status ?? $previous, 'to_status' => $result->status]);
                SendCheckAlertJob::dispatch($alert->id)->onConnection(config('operations.queue_connection'))->afterCommit();
                $check->last_alert_at = now();
            }
            if ($sendFailure) {
                $check->alert_status = $result->status;
            }
            if ($recovered) {
                $check->alert_status = null;
            }
            $check->save();
            if ($check->legacy_key) {
                DB::table('store_integration_status')->updateOrInsert(['store_id' => $check->store_id, 'check_key' => $check->legacy_key], ['status' => $result->status === 'OK' ? 'ok' : strtolower($result->status), 'error_message' => $result->error ?? ($result->status === 'OK' ? null : $result->summary), 'updated_at' => now(), 'created_at' => now()]);
            }

            return $run;
        });
    }
}
