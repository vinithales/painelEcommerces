<?php

namespace App\Livewire\Stores;

use App\Models\Store;
use Livewire\Component;
use Livewire\WithPagination;

class StoreCheckHistory extends Component
{
    use WithPagination;

    public Store $store;

    public function mount(Store $store): void
    {
        $this->store = $store;
    }

    public function render()
    {
        $executions = $this->store->checkExecutions()
            ->orderByDesc('check_executions.id')
            ->paginate(20);

        return view('livewire.stores.store-check-history', ['executions' => $executions])
            ->layout('layouts.app', ['store' => $this->store, 'title' => 'Histórico de verificações']);
    }
}
