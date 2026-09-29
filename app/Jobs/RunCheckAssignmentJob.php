<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\CheckAssignment;
use App\Services\Checks\CheckExecutionService;

class RunCheckAssignmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $assignmentId){}

    /**
     * Execute the job.
     */
    public function handle(CheckExecutionService $service): void
    {   
        $assignment = CheckAssignment::find($this->assignmentId);

        if (!$assignment) {
            return;
        }

        $service->run($assignment);
    }
}
