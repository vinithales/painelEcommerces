<?php

namespace App\Console;

use App\Models\StoreCheckRun;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('checks:dispatch')->everyMinute()->withoutOverlapping();
        $schedule->call(fn () => StoreCheckRun::where('created_at', '<', now()->subDays(config('operations.retention_days')))->delete())->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
