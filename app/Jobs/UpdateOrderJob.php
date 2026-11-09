<?php

namespace App\Jobs;

use App\Actions\Orders\UpdateOrderAction;
use App\Models\ApiOperationLog;
use App\Models\Store;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Throwable;

class UpdateOrderJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $backoff = 10;

    public function __construct(
        private readonly Store $store,
        private readonly string $orderId,
        private readonly array $attributes,
        private ?int $actorId = null,
    ) {
        $this->actorId ??= auth()->id();
    }

    public function handle(UpdateOrderAction $action): void
    {
        if ($this->actorId) {
            $user = User::findOrFail($this->actorId);
            Gate::forUser($user)->authorize('manage');
            Auth::setUser($user);
        }
        try {
            $action->execute($this->store, $this->orderId, $this->attributes);
        } finally {
            Auth::forgetGuards();
        }
    }

    public function failed(Throwable $exception): void
    {
        ApiOperationLog::create([
            'store_id' => $this->store->id,
            'operation_type' => 'update',
            'resource_type' => 'order',
            'resource_id' => $this->orderId,
            'payload' => $this->attributes,
            'response' => null,
            'status' => 'failed',
            'error_message' => "Job failed after {$this->tries} attempts: {$exception->getMessage()}",
        ]);
    }
}
