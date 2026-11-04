<?php

namespace App\Console\Commands;

use App\Services\Checks\CheckSchedulingService;
use Illuminate\Console\Command;

class DispatchDueChecks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checks:dispatch-due';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enfileira as verificações habilitadas cujo agendamento está vencido';

    /**
     * Execute the console command.
     */
    public function handle(CheckSchedulingService $checkSchedulingService): int
    {
        $checkSchedulingService->dispatchDue();

        return self::SUCCESS;
    }
}
