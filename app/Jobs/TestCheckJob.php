<?php

namespace App\Jobs;

use App\Models\StoreCheck;
use App\Services\Checks\CheckRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class TestCheckJob implements ShouldQueue
{
    use Dispatchable,Queueable,SerializesModels;

    public int $tries = 1;

    public int $timeout = 100;

    public function __construct(public array $definition, public string $key) {}

    public function handle(CheckRegistry $registry): void
    {
        $r = $registry->evaluate(new StoreCheck($this->definition));
        Cache::put($this->key, ['status' => $r->status, 'summary' => $r->summary, 'details' => $r->details, 'metrics' => $r->metrics, 'error' => $r->error], 600);
    }

    public function failed(?\Throwable $e): void
    {
        Cache::put($this->key, ['status' => 'UNKNOWN', 'summary' => 'Teste não concluído pelo worker.'], 600);
    }
}
