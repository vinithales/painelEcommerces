<?php

namespace App\Livewire\Stores;

use App\Models\Store;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class StoreCheckHistory extends Component
{
    use WithPagination;

    public Store $store;

    #[Locked]
    public ?int $selectedExecutionId = null;

    public function mount(Store $store): void
    {
        $this->store = $store;
    }

    public function openDetails(int $id): void
    {
        $execution = $this->store->checkExecutions()
            ->where('check_executions.id', $id)
            ->firstOrFail();

        $this->selectedExecutionId = $execution->id;
    }

    public function closeDetails(): void
    {
        $this->selectedExecutionId = null;
    }

    public function render()
    {
        $executions = $this->store->checkExecutions()
            ->orderByDesc('check_executions.id')
            ->paginate(20);

        $selectedExecution = $this->selectedExecutionId === null
            ? null
            : $this->store->checkExecutions()
                ->where('check_executions.id', $this->selectedExecutionId)
                ->firstOrFail();

        return view('livewire.stores.store-check-history', [
            'executions' => $executions,
            'selectedExecution' => $selectedExecution,
        ])
            ->layout('layouts.app', ['store' => $this->store, 'title' => 'Histórico de verificações']);
    }
}
