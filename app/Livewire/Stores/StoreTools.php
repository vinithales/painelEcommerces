<?php

namespace App\Livewire\Stores;

use App\Jobs\SupportOperationJob;
use App\Models\Store;
use App\Services\Operations\SupportOperations;
use App\Support\OperationsQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StoreTools extends Component
{
    public Store $store;

    public string $operation = 'api_auth';

    public string $identifier = '';

    #[Locked]
    public ?string $taskKey = null;

    public function mount(Store $store): void
    {
        $this->store = $store;
    }

    public function boot(): void
    {
        Gate::authorize('operate');
    }

    public function execute(): void
    {
        $this->validate(['operation' => Rule::in(SupportOperations::API), 'identifier' => 'nullable|string|max:255']);
        if (in_array($this->operation, ['product_id', 'product_sku', 'order_id'])) {
            $this->validate(['identifier' => 'required|string|max:255']);
        }
        if (in_array($this->operation, ['product_id', 'order_id'])) {
            $this->validate(['identifier' => 'required|regex:/^[0-9]+$/']);
        }
        $this->taskKey = 'support:'.auth()->id().':'.Str::uuid();
        Cache::put($this->taskKey, ['complete' => false], 600);
        SupportOperationJob::dispatch($this->store->id, $this->operation, ['identifier' => $this->identifier], $this->taskKey, auth()->id())->onConnection(OperationsQueue::connection());
    }

    public function render()
    {
        return view('livewire.stores.store-tools', ['task' => $this->taskKey ? Cache::get($this->taskKey) : null])->layout('layouts.app', ['store' => $this->store, 'title' => 'Ferramentas de API']);
    }
}
