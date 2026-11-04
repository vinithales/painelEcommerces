<?php

namespace App\Livewire\Stores;

use App\Models\CheckAssignment;
use App\Models\CheckDefinition;
use App\Models\CheckExecution;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CheckReports extends Component
{
    use WithPagination;

    #[Url]
    public ?int $definitionId = null;

    #[Url]
    public ?int $storeId = null;

    #[Url]
    public string $status = 'all';

    #[Locked]
    public ?int $selectedExecutionId = null;

    public const STATUS_LABELS = [
        'all' => 'Todos os resultados',
        'attention' => 'Falhas e erros',
        'failed' => 'Condição não atendida',
        'error' => 'Erro de execução',
        'success' => 'Sucesso',
        'active' => 'Na fila ou executando',
        'pending' => 'Na fila',
        'running' => 'Executando',
        'never' => 'Sem execução',
    ];

    public function boot(): void
    {
        Gate::authorize('operate');
    }

    public function updated($property): void
    {
        if (in_array($property, ['definitionId', 'storeId', 'status'], true)) {
            $this->resetPage();
            $this->closeDetails();
        }
    }

    private function assignments(): Builder
    {
        return CheckAssignment::query()
            ->when($this->definitionId, fn ($query) => $query->where('check_definition_id', $this->definitionId))
            ->when($this->storeId, fn ($query) => $query->where('store_id', $this->storeId));
    }

    private function selectedExecution(): ?CheckExecution
    {
        return $this->selectedExecutionId === null ? null : CheckExecution::with('checkAssignment.store')
            ->whereHas('checkAssignment', fn ($query) => $query->whereIn('id', $this->assignments()->select('id')))
            ->findOrFail($this->selectedExecutionId);
    }

    public function openDetails(int $id): void
    {
        $this->selectedExecutionId = $id;
        $this->selectedExecution();
    }

    public function closeDetails(): void
    {
        $this->selectedExecutionId = null;
    }

    public function render()
    {
        $scope = $this->assignments();
        $counts = ['all' => (clone $scope)->count()];
        foreach (['attention', 'success', 'active', 'never'] as $status) {
            $counts[$status] = (clone $scope)->reportStatus($status)->count();
        }

        $assignments = (clone $scope)
            ->with([
                'store', 'checkDefinition', 'latestExecution',
                'latestStartedExecution' => fn ($query) => $query->select('check_executions.id', 'check_executions.check_assignment_id', 'started_at', 'finished_at'),
            ])
            ->when($this->status !== 'all', fn ($query) => $query->reportStatus($this->status))
            ->orderBy('check_definition_id')->orderBy('store_id')->paginate(25);

        return view('livewire.stores.check-reports', [
            'assignments' => $assignments,
            'counts' => $counts,
            'lastRunAt' => CheckExecution::whereIn('check_assignment_id', (clone $scope)->select('id'))->max('started_at'),
            'definitions' => CheckDefinition::orderBy('name')->get(['id', 'name']),
            'stores' => Store::orderBy('name')->get(['id', 'name']),
            'selectedExecution' => $this->selectedExecution(),
        ])->layout('layouts.app', ['title' => 'Relatórios de verificações']);
    }
}
