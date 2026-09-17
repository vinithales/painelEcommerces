<?php

namespace App\Services\IntegrationCheckers;

use App\Models\Store;
use App\Services\Checks\CheckConfiguration;
use App\Services\Checks\CheckDispatcher;
use Illuminate\Database\Eloquent\Collection;

class RunStoreChecks
{
    public function definitions(Store $store): Collection
    {
        foreach (['robots_txt' => 'ROBOTS_TXT', 'indexability' => 'META_ROBOTS'] as $key => $type) {
            $store->checks()->firstOrCreate(['legacy_key' => $key], ['name' => $type === 'ROBOTS_TXT' ? 'robots.txt' : 'Indexação', 'level' => 'SITE', 'check_type' => $type, 'configuration' => CheckConfiguration::defaults($type), 'interval_seconds' => 3600, 'enabled' => true, 'next_run_at' => now()]);
        }

        return $store->checks()->whereNotNull('legacy_key')->get();
    }

    public function handle(Store $store): array
    {
        foreach ($this->definitions($store) as $check) {
            app(CheckDispatcher::class)->dispatch($check, true);
        }

        return [];
    }
}
