<?php

namespace App\Actions\Products;

use App\Models\ProductSnapshot;
use App\Services\PlatformApi\PlatformApiAdapterFactory;
use RuntimeException;

class RollbackProductAction
{
    public function __construct(private readonly PlatformApiAdapterFactory $factory)
    {
    }

    public function execute(ProductSnapshot $snapshot): array
    {
        if ($snapshot->wasReverted()) {
            throw new RuntimeException("Snapshot #{$snapshot->id} was already reverted.");
        }

        $store = $snapshot->store;
        $adapter = $this->factory->make($store);

        $result = $adapter->updateProduct($snapshot->resource_id, $snapshot->data_before);

        $snapshot->update(['reverted_at' => now()]);

        return $result;
    }
}
