<?php

namespace App\Actions\Stores;

use App\Models\Store;
use Illuminate\Support\Facades\DB;

class CreateStoreAction
{
    public function execute(array $storeData, array $integrationData): Store
    {
        return DB::transaction(function () use ($storeData, $integrationData) {
            $store = Store::create($storeData);

            $store->syncIntegration($integrationData);

            return $store->fresh(['wooCommerceIntegration', 'magento2Integration']);
        });
    }
}