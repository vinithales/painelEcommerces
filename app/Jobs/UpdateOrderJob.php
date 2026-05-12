<?php

namespace App\Jobs;

use App\Actions\Orders\UpdateOrderAction;
use App\Models\ApiOperationLog;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class UpdateOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        private readonly Store $store,
        private readonly string $orderId,
        private readonly array $attributes,
    ) {
    }

    public function handle(UpdateOrderAction $action): void
    {
        $action->execute($this->store, $this->orderId, $this->attributes);
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
