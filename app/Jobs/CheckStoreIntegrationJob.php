<?php

namespace App\Jobs;

use App\Mail\StoreCheckAlert;
use App\Models\Store;
use App\Services\IntegrationCheckers\RunStoreChecks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CheckStoreIntegrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Store $store;

    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    public function handle(): void
    {
        $this->store->loadMissing(['wooCommerceIntegration', 'magento2Integration']);
        $results = app()->make(RunStoreChecks::class)->handle($this->store);

        foreach ($results as $result) {
            $this->persistResult($result);
        }

        $failingResults = array_filter(
            $results,
            fn (array $r) => ! in_array($r['status'], ['ok', 'indexable'], true),
        );

        if ($failingResults !== []) {
            $recipients = ['vinicius@bring.com.br', 'guiserinoli@bring.com.br'];

            (new StoreCheckAlert($this->store, array_values($failingResults)))
                ->send($recipients);
        }
    }

    private function persistResult(array $result): void
    {
        $attributes = [
            'store_id' => $this->store->id,
            'check_key' => $result['check_key'],
        ];

        $payload = [
            'status' => $result['status'],
            'error_message' => $result['error'] ?? null,
            'updated_at' => now(),
        ];

        $updated = DB::table('store_integration_status')
            ->where($attributes)
            ->update($payload);

        if ($updated === 0) {
            DB::table('store_integration_status')->updateOrInsert($attributes, [
                ...$attributes,
                ...$payload,
                'created_at' => now(),
            ]);
        }
    }
}