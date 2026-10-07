<?php

namespace App\Services\Checks;

use App\Actions\Checks\CheckAssignmentsAction;
use App\Models\CheckAssignment;
use App\Jobs\RunCheckAssignmentJob;
use Illuminate\Support\Facades\DB;

class CheckSchedulingService
{
    public function __construct(
        private readonly CheckAssignmentsAction $checkAssignmentsAction
    ) {
    }
    public function dispatchDue()
    {
        $this->checkAssignmentsAction
            ->dueCheckAssignments()
            ->chunkById(100, function ($assignments) {
                foreach ($assignments as $assignment) {
                    DB::transaction(function () use ($assignment) {
                        $lockedAssignment = CheckAssignment::query()
                            ->whereKey($assignment->id)
                            ->lockForUpdate()
                            ->first();

                        if (
                            !$lockedAssignment
                            || !$lockedAssignment->enabled
                            || $lockedAssignment->next_run_at === null
                            || $lockedAssignment->next_run_at->isFuture()
                        ) {
                            return;
                        }

                        $definition = $lockedAssignment->checkDefinition;
                        $execution = $lockedAssignment->executions()->create([
                            'definition_snapshot' => $definition->only([
                                'name',
                                'level',
                                'executor_type',
                                'configuration',
                                'assertions',
                            ]),
                            'status' => 'pending',
                        ]);
                        $lockedAssignment->update([
                            'next_run_at' => now()->addMinutes($lockedAssignment->interval_minutes),
                        ]);

                        RunCheckAssignmentJob::dispatch($lockedAssignment->id, $execution->id)->onConnection();

                    });
                }
            });
    }
}
