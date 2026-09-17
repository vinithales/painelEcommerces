<?php

namespace App\Services\Checks;

use App\Jobs\RunCheckJob;
use App\Models\AuditEvent;
use App\Models\StoreCheck;
use App\Support\OperationsQueue;
use Illuminate\Support\Str;

class CheckDispatcher
{
    public function dispatch(StoreCheck $check, bool $manual = false, ?int $actor = null): bool
    {
        $query = StoreCheck::whereKey($check->id)->where('enabled', true)->where(fn ($q) => $q->whereNull('claimed_until')->orWhere('claimed_until', '<=', now()));
        if (! $manual) {
            $query->where(fn ($q) => $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now()));
        }
        $token = (string) Str::uuid();
        if (! $query->update(['claimed_until' => now()->addMinutes(5), 'claim_token' => $token])) {
            return false;
        }
        try {
            RunCheckJob::dispatch($check->id, $manual, $token)->onConnection(OperationsQueue::connection());
            if ($manual) {
                AuditEvent::record($check->store_id, 'check', 'run', ['check_id' => $check->id], actor: $actor);
            }

            return true;
        } catch (\Throwable $e) {
            $check->update(['claimed_until' => null]);
            throw $e;
        }
    }

    public function due(): int
    {
        $count = 0;
        StoreCheck::where('enabled', true)->where(fn ($q) => $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('claimed_until')->orWhere('claimed_until', '<=', now()))
            ->chunkById(100, function ($checks) use (&$count) {
                foreach ($checks as $check) {
                    try {
                        $count += (int) $this->dispatch($check);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            });

        return $count;
    }
}
