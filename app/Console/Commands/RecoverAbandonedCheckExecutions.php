<?php

namespace App\Console\Commands;

use App\Services\Checks\RecoverAbandonedCheckExecutionsService;
use Illuminate\Console\Command;

class RecoverAbandonedCheckExecutions extends Command
{
    protected $signature = 'checks:recover-abandoned';

    protected $description = 'Finaliza como erro as verificações em execução há mais de 5 minutos';

    public function handle(RecoverAbandonedCheckExecutionsService $service): int
    {
        $count = $service->recover();
        $this->info("Execuções recuperadas: {$count}.");

        return self::SUCCESS;
    }
}
