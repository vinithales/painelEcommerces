<?php

namespace App\Actions\Stores;

use App\Models\Store;
use Illuminate\Support\Facades\DB;

class UpdateStoreAction
{
    public function execute(Store $store, array $storeData, array $integrationData): Store
    {
        return DB::transaction(function () use ($store, $storeData, $integrationData) {
            $samePlatform = $store->platform === $storeData['platform'];
            $credentials = $samePlatform ? $store->integrationCredentials() : [];
            $store->update($storeData);

            $store->replaceIntegration(array_merge($credentials, array_filter($integrationData, fn ($value) => $value !== null && $value !== '')));
            \App\Models\AuditEvent::record($store->id, 'store', 'update', $storeData);

            return $store->fresh(['wooCommerceIntegration', 'magento2Integration']);
        });
    }
}
