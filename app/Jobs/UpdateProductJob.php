<?php

namespace App\Jobs;

use App\Actions\Products\UpdateProductAction;
use App\Models\ApiOperationLog;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class UpdateProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        private readonly Store $store,
        private readonly string $productId,
        private readonly array $attributes,
    ) {
    }

    public function handle(UpdateProductAction $action): void
    {
        $action->execute($this->store, $this->productId, $this->attributes);
    }

    public function failed(Throwable $exception): void
    {
        ApiOperationLog::create([
            'store_id' => $this->store->id,
            'operation_type' => 'update',
            'resource_type' => 'product',
            'resource_id' => $this->productId,
            'payload' => $this->attributes,
            'response' => null,
            'status' => 'failed',
            'error_message' => "Job failed after {$this->tries} attempts: {$exception->getMessage()}",
        ]);
    }
}
