<?php

namespace App\Actions\Stores;

use App\Models\Store;
use Illuminate\Support\Facades\DB;

class UpdateStoreAction
{
    public function execute(Store $store, array $storeData, array $integrationData): Store
    {
        return DB::transaction(function () use ($store, $storeData, $integrationData) {
            $store->update($storeData);

            $store->replaceIntegration($integrationData);

            return $store->fresh(['wooCommerceIntegration', 'magento2Integration']);
        });
    }
}