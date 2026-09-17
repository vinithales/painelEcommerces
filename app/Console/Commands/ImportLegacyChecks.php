<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\IntegrationCheckers\RunStoreChecks;
use Illuminate\Console\Command;

class ImportLegacyChecks extends Command
{
    protected $signature = 'checks:import-legacy';

    protected $description = 'Convergir checks legados para o motor periódico';

    public function handle(): int
    {
        Store::chunkById(100, function ($stores) {
            foreach ($stores as $store) {
                app(RunStoreChecks::class)->definitions($store);
            }
        });
        $this->info('Checks legados importados de forma idempotente.');

        return 0;
    }
}
