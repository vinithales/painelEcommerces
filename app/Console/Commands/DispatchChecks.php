<?php

namespace App\Console\Commands;

use App\Services\Checks\CheckDispatcher;
use Illuminate\Console\Command;

class DispatchChecks extends Command
{
    protected $signature = 'checks:dispatch';

    protected $description = 'Enfileirar verificações vencidas';

    public function handle(CheckDispatcher $dispatcher): int
    {
        $this->info($dispatcher->due().' checks enfileirados.');

        return 0;
    }
}
