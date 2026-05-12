<?php

namespace App\Livewire\Stores;

use App\Jobs\CheckStoreIntegrationJob;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('SEO Checks')]
class StoreChecks extends Component
{
    use WithPagination;

    public string $search = '';

    public string $platform = 'all';

    public string $statusFilter = 'all';

    /** @var array<int, int> */
    public array $selected = [];

    public bool $selectAll = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->selected = [];
        $this->selectAll = false;
    }

    public function updatingPlatform(): void
    {
        $this->resetPage();
        $this->selected = [];
        $this->selectAll = false;
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
        $this->selected = [];
        $this->selectAll = false;
    }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            $this->selected = $this->getFilteredQuery()->pluck('id')->toArray();
        } else {
            $this->selected = [];
        }
    }

    public function runCheck(int $storeId): void
    {
        $store = Store::findOrFail($storeId);
        CheckStoreIntegrationJob::dispatchSync($store);

        session()->flash('status', "Check for \"{$store->name}\" completed.");
    }

    public function runSelectedChecks(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $stores = Store::whereIn('id', $this->selected)->get();

        foreach ($stores as $store) {
            CheckStoreIntegrationJob::dispatchSync($store);
        }

        $count = $stores->count();
        $this->selected = [];
        $this->selectAll = false;

        session()->flash('status', "Checks completed for {$count} store(s).");
    }

    public function render()
    {
        $query = $this->getFilteredQuery();

        $stores = $query->paginate(15);

        return view('livewire.stores.store-checks', [
            'stores' => $stores,
        ]);
    }

    private function getFilteredQuery()
    {
        $checkKeys = ['robots_txt', 'indexability'];

        return Store::query()
            ->select('stores.*')
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('store_url', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->platform !== 'all', fn ($q) => $q->where('platform', $this->platform))
            ->when($this->statusFilter !== 'all', function ($query) use ($checkKeys) {
                $isProblematic = $this->statusFilter === 'issues';

                $query->whereExists(function ($sub) use ($checkKeys, $isProblematic) {
                    $sub->select(DB::raw(1))
                        ->from('store_integration_status')
                        ->whereColumn('store_id', 'stores.id')
                        ->whereIn('check_key', $checkKeys)
                        ->when(
                            $isProblematic,
                            fn ($s) => $s->whereNotIn('status', ['ok', 'indexable']),
                            fn ($s) => $s->whereIn('status', ['ok', 'indexable']),
                        );
                });
            })
            ->with([
                'wooCommerceIntegration',
                'magento2Integration',
            ])
            ->latest();
    }

    public function getStoreStatuses(int $storeId): array
    {
        return DB::table('store_integration_status')
            ->where('store_id', $storeId)
            ->pluck('status', 'check_key')
            ->toArray();
    }

    public function getStoreErrors(int $storeId): array
    {
        return DB::table('store_integration_status')
            ->where('store_id', $storeId)
            ->whereNotNull('error_message')
            ->pluck('error_message', 'check_key')
            ->toArray();
    }
}
