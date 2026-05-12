<?php

namespace App\Actions\Stores;

use App\Models\Store;

class DeleteStoreAction
{
    public function execute(Store $store): void
    {
        $store->delete();
    }
}