<?php

namespace App\Livewire\Stores;

use App\Actions\Stores\DeleteStoreAction;
use App\Models\Store;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Stores')]
class IndexStores extends Component
{
    use WithPagination;

    public string $search = '';

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
        \Illuminate\Support\Facades\Gate::authorize('configure');
        $store = Store::query()->findOrFail($storeId);

        $action->execute($store);

        session()->flash('status', 'Store deleted successfully.');
    }

    public function render()
    {
        $stores = Store::query()
            ->with(['wooCommerceIntegration', 'magento2Integration'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($innerQuery) {
                    $innerQuery
                        ->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('store_url', 'like', '%' . $this->search . '%')
                        ->orWhere('panel_prefix', 'like', '%' . $this->search . '%');
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
