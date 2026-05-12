<?php

namespace App\Console;

use App\Jobs\CheckStoreIntegrationJob;
use App\Models\Store;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function (){
            Store::chunk(50, function ($stores) {
                foreach ($stores as $store){
                    CheckStoreIntegrationJob::dispatch($store);
                }
            });
        })->dailyAt('12:00');
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
