<?php

namespace App\Jobs;

use App\Models\StoreCheck;
use App\Services\Checks\CheckRunner;
use App\Support\Redactor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class RunCheckJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries = 1;

    public int $timeout = 100;

    public function __construct(public int $checkId, public bool $manual = false, public ?string $claimToken = null) {}

    public function handle(CheckRunner $runner): void
    {
        $check = StoreCheck::find($this->checkId);
        if (! $check || ($this->claimToken !== null && $check->claim_token !== $this->claimToken)) {
            return;
        }
        $lock = Cache::lock('check:'.$check->id, 120);
        if (! $lock->get()) {
            return;
        }
        try {
            $runner->run($check, $this->manual);
        } finally {
            StoreCheck::whereKey($check->id)->where('claim_token', $this->claimToken)->update(['claimed_until' => null, 'claim_token' => null]);
            $lock->release();
        }
    }

    public function failed(?\Throwable $e): void
    {
        $check = StoreCheck::find($this->checkId);
        if (! $check || ($this->claimToken !== null && $check->claim_token !== $this->claimToken)) {
            return;
        }
        $check->runs()->create(['status' => 'UNKNOWN', 'summary' => 'Worker não concluiu a execução.', 'started_at' => now(), 'finished_at' => now(), 'duration_ms' => 0, 'manual' => $this->manual, 'error_message' => Redactor::clean($e?->getMessage())]);
        $check->update(['claimed_until' => null, 'last_status' => 'UNKNOWN', 'last_run_at' => now(), 'next_run_at' => now()->addSeconds($check->interval_seconds), 'failure_count' => 0]);
    }
}
