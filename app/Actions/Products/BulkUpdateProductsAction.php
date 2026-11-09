<?php

namespace App\Actions\Products;

use App\Jobs\UpdateProductJob;
use App\Models\Store;
use App\Services\PlatformApi\ProductFields;
use App\Services\PlatformApi\ProductsApiService;
use Illuminate\Support\Facades\Bus;

class BulkUpdateProductsAction
{
    public function __construct(private readonly ProductsApiService $service) {}

    public function execute(Store $store, array $productIds, array $attributes, bool $dryRun = false): array
    {
        ProductFields::validate($store, $attributes);
        validator(['ids' => $productIds], ['ids' => 'required|array|max:100', 'ids.*' => 'required|string|max:255'])->validate();
        if ($dryRun) {
            $previews = [];
            foreach ($productIds as $productId) {
                $previews[] = $this->service->update($store, (string) $productId, $attributes, dryRun: true);
            }

            return $previews;
        }

        $batch = Bus::batch(array_map(fn ($id) => new UpdateProductJob($store, (string) $id, $attributes), $productIds))
            ->name('Edição de produtos')->allowFailures()
            ->withOption('store_id', $store->id)->withOption('resource', 'product')
            ->withOption('user_id', auth()->id())->dispatch();

        return ['dispatched' => count($productIds), 'batch_id' => $batch->id];
    }
}
