<?php

namespace App\Console\Commands;

use App\Services\Checks\CheckExecutionRetentionService;
use Illuminate\Console\Command;

class CleanCheckExecutionResults extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checks:clean-execution-results';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove o corpo das respostas de verificações finalizadas há mais de 90 dias';

    /**
     * Execute the console command.
     */
    public function handle(CheckExecutionRetentionService $service): int
    {
        $count = $service->clean();
        $this->info("Resultados de execuções limpos: {$count}.");

        return self::SUCCESS;
    }
}

