<?php

namespace App\Jobs;

use App\Models\CheckAssignment;
use App\Models\CheckExecution;
use App\Services\Checks\CheckExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class RunCheckAssignmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public ?int $executionId = null;

    public bool $failOnTimeout = true;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $assignmentId, ?int $executionId = null)
    {
        $this->executionId = $executionId;
        $this->tries = $executionId ? 300 : 1;
    }

    /**
     * Execute the job.
     */
    public function handle(CheckExecutionService $service): void
    {
        $assignment = CheckAssignment::find($this->assignmentId);
        $execution = $this->executionId
            ? CheckExecution::where('check_assignment_id', $this->assignmentId)->find($this->executionId)
            : null;

        if ($this->executionId && (! $execution || $execution->status !== 'pending')) {
            return;
        }

        if (! $assignment) {
            $this->failed(new \RuntimeException('A associação com a loja não está mais disponível.'));

            return;
        }

        if ($service->run($assignment, $execution) === null && $execution) {
            $this->failed(new \RuntimeException('A loja ou a verificação não está mais disponível ou habilitada.'));
        }
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->executionId) {
            CheckExecution::where('check_assignment_id', $this->assignmentId)
                ->whereKey($this->executionId)
                ->whereIn('status', ['pending', 'running'])
                ->update(['status' => 'error', 'error_message' => $exception->getMessage(), 'finished_at' => now()]);
        }
    }

    public function middleware(): array
    {
        $middleware = (new WithoutOverlapping('check-assignment:'.$this->assignmentId))->expireAfter(150);
        $locks = [$this->executionId ? $middleware->releaseAfter(5) : $middleware->dontRelease()];
        $type = $this->executionId
            ? CheckExecution::find($this->executionId)?->definition_snapshot['executor_type'] ?? null
            : CheckAssignment::find($this->assignmentId)?->checkDefinition?->executor_type;
        if ($type === 'browser') {
            $locks[] = (new WithoutOverlapping('browser-check'))->shared()->releaseAfter(5)->expireAfter(60);
        }

        return $locks;
    }
}
