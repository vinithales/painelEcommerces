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
    protected $description = 'Deleta registros de execução de verificação antigos e limpa os resultados das execuções antigas';

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

