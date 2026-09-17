<?php

namespace App\Livewire\Stores;

use App\Models\ApiOperationLog;
use App\Models\Store;
use Livewire\Component;

class StoreOverview extends Component
{
    public Store $store;

    public function mount(Store $store): void
    {
        $this->store = $store;
    }

    public function render()
    {
        $checks = $this->store->checks()->get();
        $levels = [];
        foreach (['API', 'SITE', 'APP', 'SERVER'] as $level) {
            $statuses = $checks->where('enabled', true)->where('level', $level)->pluck('last_status');
            $levels[$level] = $statuses->contains('CRITICAL') ? 'CRITICAL' : ($statuses->contains('WARNING') ? 'WARNING' : ($statuses->isEmpty() || $statuses->contains('UNKNOWN') ? 'UNKNOWN' : 'OK'));
        }

        return view('livewire.stores.store-overview', ['checks' => $checks, 'levels' => $levels, 'audits' => $this->store->audits()->latest()->limit(15)->get(), 'apiLogs' => ApiOperationLog::where('store_id', $this->store->id)->latest()->limit(10)->get()])->layout('layouts.app', ['store' => $this->store, 'title' => 'Visão operacional']);
    }
}
