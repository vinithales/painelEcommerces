<?php

namespace App\Jobs;

use App\Models\Store;
use App\Services\IntegrationCheckers\RunStoreChecks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CheckStoreIntegrationJob implements ShouldQueue
{
    use Dispatchable,Queueable,SerializesModels;

    public function __construct(private readonly Store $store) {}

    public function handle(RunStoreChecks $runner): void
    {
        $runner->handle($this->store);
    }
}
