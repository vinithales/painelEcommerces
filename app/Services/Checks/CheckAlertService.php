<?php

namespace App\Services\Checks;

use App\Models\CheckAssignment;
use Illuminate\Support\Facades\DB;

class CheckAlertService
{
    public function evaluate(CheckAssignment $assignment): void
    {
        DB::transaction(function () use ($assignment): void {
            $assignment = CheckAssignment::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->first();

            if (! $assignment || ! $assignment->alert_enabled) {
                return;
            }

            $incident = $assignment->incidents()->whereNull('resolved_at')->first();

            if ($incident) {
                $threshold = $assignment->recovery_threshold;

                if ($threshold < 1) {
                    return;
                }

                $executions = $assignment->executions()
                    ->whereNotNull('finished_at')
                    ->orderByDesc('id')
                    ->limit($threshold)
                    ->get(['status', 'outcome']);

                if ($executions->count() === $threshold && $executions->every(
                    fn ($execution) => $execution->status === 'completed' && $execution->outcome === 'success'
                )) {
                    $incident->update(['resolved_at' => now()]);
                }

                return;
            }

            $threshold = $assignment->failure_threshold;

            if ($threshold < 1) {
                return;
            }

            $executions = $assignment->executions()
                ->whereNotNull('finished_at')
                ->orderByDesc('id')
                ->limit($threshold)
                ->get(['status', 'outcome']);

            if ($executions->count() !== $threshold || $executions->contains(
                fn ($execution) => ! (
                    $execution->status === 'error'
                    || ($execution->status === 'completed' && $execution->outcome === 'failed')
                )
            )) {
                return;
            }

            $assignment->incidents()->create([
                'opened_at' => now(),
            ]);
        });
    }
}
