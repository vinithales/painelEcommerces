<?php

namespace App\Actions\Products;

use App\Models\AuditEvent;
use App\Models\ProductSnapshot;
use App\Services\PlatformApi\ProductFields;
use App\Services\PlatformApi\ProductsApiService;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class RollbackProductAction
{
    public function __construct(private readonly ProductsApiService $service) {}

    public function execute(ProductSnapshot $snapshot): array
    {
        return Cache::lock('product:'.$snapshot->store_id.':'.hash('sha256', $snapshot->resource_id), 120)->block(5, function () use ($snapshot) {
            $snapshot->refresh();
            if ($snapshot->wasReverted() || ! $snapshot->data_after) {
                throw new RuntimeException('Snapshot inválido ou já revertido.');
            }
            $fields = ProductFields::rules($snapshot->store);
            $before = array_intersect_key($snapshot->data_before, $fields);
            if (! $before) {
                throw new RuntimeException('Snapshot sem campos graváveis.');
            }
            $current = ProductFields::values($snapshot->store, $this->service->get($snapshot->store, $snapshot->resource_id)['data']);
            foreach (array_intersect_key($snapshot->data_after, $before) as $key => $value) {
                if (($current[$key] ?? null) != $value) {
                    throw new RuntimeException('Produto mudou após o snapshot. Atualize os dados antes de decidir a correção.');
                }
            }
            $result = $this->service->update($snapshot->store, $snapshot->resource_id, $before);
            $snapshot->update(['reverted_at' => now()]);
            AuditEvent::record($snapshot->store_id, 'product', 'rollback', ['snapshot_id' => $snapshot->id, 'fields' => array_keys($before)]);

            return $result;
        });
    }
}
