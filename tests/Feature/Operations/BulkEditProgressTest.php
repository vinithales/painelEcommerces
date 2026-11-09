<?php

namespace Tests\Feature\Operations;

use App\Livewire\Stores\StoreProducts;
use App\Models\Store;
use App\Models\User;
use App\Services\PlatformApi\ProductsApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class BulkEditProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_jobs_update_the_batch_counters(): void
    {
        $store = Store::create(['name' => 'Loja', 'store_url' => 'https://example.com', 'platform' => 'woocommerce']);
        $this->mock(\App\Actions\Products\UpdateProductAction::class, fn ($mock) => $mock->shouldReceive('execute')->twice()->andReturn([]));
        $this->mock(ProductsApiService::class, fn ($mock) => $mock->shouldReceive('list')->once()->andReturn(['data' => [], 'total' => 0]));
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(StoreProducts::class, ['store' => $store])
            ->call('openBulkApplyModal', ['1', '2'])->set('bulkValue', 'publish')->call('applyBulk')
            ->assertHasNoErrors()->assertSet('bulkProgress.succeeded', 2)
            ->assertSet('bulkProgress.percent', 100)->assertSet('bulkProgress.active', false);
    }

    public function test_progress_counts_success_and_failure_and_survives_reload(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => 'admin']);
        $store = Store::create(['name' => 'Loja', 'store_url' => 'https://example.com', 'platform' => 'woocommerce']);
        $this->mock(ProductsApiService::class, fn ($mock) => $mock->shouldReceive('list')->once()->andReturn(['data' => [], 'total' => 0]));
        $component = Livewire::actingAs($user)->test(StoreProducts::class, ['store' => $store])
            ->call('openBulkApplyModal', ['1', '2'])->set('bulkField', 'price')->set('bulkValue', '10')
            ->call('applyBulk')->assertHasNoErrors()
            ->assertSet('bulkProgress.active', true)->assertSet('bulkProgress.percent', 0);
        $batch = Bus::findBatch($component->get('bulkBatchId'));
        $this->assertSame(2, $batch->totalJobs);
        $batch->recordSuccessfulJob('first');
        $component->call('pollBulkProgress')->assertSet('bulkProgress.percent', 50)
            ->assertSet('bulkProgress.succeeded', 1)->assertSet('bulkProgress.active', true);
        $component->call('dismissBulkProgress', $batch->id)->assertSet('bulkBatchId', $batch->id);
        $component->call('applyBulk')->assertHasErrors('bulk');
        $this->assertDatabaseCount('job_batches', 1);
        Livewire::test(StoreProducts::class, ['store' => $store])
            ->assertSet('bulkBatchId', $batch->id)->assertSet('bulkProgress.percent', 50);
        $batch->recordFailedJob('second', new \RuntimeException('API unavailable'));
        $component->call('pollBulkProgress')->assertSet('bulkProgress.percent', 100)
            ->assertSet('bulkProgress.active', false)->assertSet('bulkProgress.failed', 1)
            ->assertSee('concluída com falhas')->assertDispatched('resource-rows');
        // A finished batch must not repeatedly reload the product listing.
        $component->call('pollBulkProgress')->assertSet('bulkProgress.active', false);
        Livewire::test(StoreProducts::class, ['store' => $store])->assertSet('bulkProgress', []);
        $component->call('dismissBulkProgress', 'old-batch')->assertSet('bulkBatchId', $batch->id);
        $component->call('dismissBulkProgress', $batch->id)->assertSet('bulkBatchId', null)->assertSet('bulkProgress', []);
    }

    public function test_all_successful_jobs_show_completion_and_other_stores_do_not_see_the_batch(): void
    {
        Queue::fake();
        $store = Store::create(['name' => 'Loja', 'store_url' => 'https://example.com', 'platform' => 'woocommerce']);
        $other = Store::create(['name' => 'Outra', 'store_url' => 'https://other.example.com', 'platform' => 'woocommerce']);
        $this->mock(ProductsApiService::class, fn ($mock) => $mock->shouldReceive('list')->once()->andReturn(['data' => [], 'total' => 0]));
        $component = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(StoreProducts::class, ['store' => $store])
            ->call('openBulkApplyModal', ['1'])->set('bulkValue', 'publish')->call('applyBulk');
        Livewire::test(StoreProducts::class, ['store' => $other])->assertSet('bulkBatchId', null)->assertSet('bulkProgress', []);
        Bus::findBatch($component->get('bulkBatchId'))->recordSuccessfulJob('first');
        $component->call('pollBulkProgress')->assertSet('bulkProgress.percent', 100)
            ->assertSet('bulkProgress.failed', 0)->assertSet('bulkProgress.active', false)
            ->assertSee('Edição em massa concluída');
    }
}
