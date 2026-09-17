<?php

namespace App\Actions\Stores;

use App\Models\AuditEvent;
use App\Models\Store;

class DeleteStoreAction
{
    public function execute(Store $store): void
    {
        AuditEvent::record($store->id, 'store', 'delete', ['name' => $store->name]);
        $store->delete();
    }
}
