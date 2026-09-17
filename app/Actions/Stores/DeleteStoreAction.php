<?php

namespace App\Actions\Stores;

use App\Models\Store;

class DeleteStoreAction
{
    public function execute(Store $store): void
    {
        \App\Models\AuditEvent::record($store->id, 'store', 'delete', ['name' => $store->name]);
        $store->delete();
    }
}
