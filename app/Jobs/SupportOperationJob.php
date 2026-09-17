<?php

namespace App\Jobs;

use App\Models\AuditEvent;
use App\Models\Store;
use App\Models\User;
use App\Services\Operations\SupportOperations;
use App\Support\Redactor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class SupportOperationJob implements ShouldQueue
{
    use Dispatchable,Queueable,SerializesModels;

    public int $tries = 1;

    public int $timeout = 110;

    public function __construct(public int $storeId, public string $operation, public array $parameters, public string $key, public int $actor) {}

    public function handle(SupportOperations $service): void
    {
        $start = microtime(true);
        try {
            $user = User::findOrFail($this->actor);
            Gate::forUser($user)->authorize('operate');
            $result = Redactor::clean($service->run(Store::findOrFail($this->storeId), $this->operation, $this->parameters));
            Cache::put($this->key, ['complete' => true, 'result' => $result, 'duration_ms' => (int) ((microtime(true) - $start) * 1000)], 600);
            AuditEvent::record($this->storeId, 'support', $this->operation, $this->parameters, ($result['status'] ?? '') === 'FAILED' ? 'failed' : 'ok', $result['error'] ?? null, $this->actor);
        } catch (\Throwable $e) {
            $error = Redactor::clean($e->getMessage());
            Cache::put($this->key, ['complete' => true, 'error' => $error], 600);
            AuditEvent::record($this->storeId, 'support', $this->operation, $this->parameters, 'failed', $error, $this->actor);
        }
    }

    public function failed(?\Throwable $e): void
    {
        Cache::put($this->key, ['complete' => true, 'error' => 'Worker não concluiu a operação.'], 600);
    }
}
