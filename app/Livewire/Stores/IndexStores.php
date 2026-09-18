<?php

namespace App\Livewire\Stores;

use App\Actions\Stores\DeleteStoreAction;
use App\Models\Store;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Lojas')]
class IndexStores extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $platform = 'all';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPlatform(): void
    {
        $this->resetPage();
    }

    public function delete(DeleteStoreAction $action, int $storeId): void
    {
        Gate::authorize('configure');
        $store = Store::query()->findOrFail($storeId);

        $action->execute($store);

        session()->flash('status', 'Store deleted successfully.');
    }

    public function render()
    {
        $stores = Store::query()
            ->with(['checks', 'serverConnection'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($innerQuery) {
                    $innerQuery
                        ->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('store_url', 'like', '%'.$this->search.'%')
                        ->orWhere('panel_prefix', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->platform !== 'all', fn ($query) => $query->where('platform', $this->platform))
            ->latest()
            ->paginate(8);

        return view('livewire.stores.index-stores', [
            'stores' => $stores,
            'storeCount' => Store::count(),
            'wooCommerceCount' => Store::query()->where('platform', Store::PLATFORM_WOOCOMMERCE)->count(),
            'magento2Count' => Store::query()->where('platform', Store::PLATFORM_MAGENTO2)->count(),
        ]);
    }
}
