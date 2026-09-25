<?php

namespace App\Livewire\Stores;

use App\Actions\Checks\CheckDefinitionValidator;
use App\Models\CheckDefinition;
use App\Models\Store;
use App\Services\Checks\CheckDefinitionService;
use JsonException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CheckDefinitions extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public bool $formOpen = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $level = 'SITE';

    public string $executorType = 'http';

    public string $configurationJson = "{\n    \"path\": \"/\",\n    \"method\": \"GET\",\n    \"timeout\": 10,\n    \"follow_redirects\": false\n}";

    public string $assertionsJson = "[\n    {\n        \"field\": \"status_code\",\n        \"operator\": \"equals\",\n        \"expected\": 200\n    }\n]";

    public array $storeAssignments = [];

    public function boot(): void
    {
        Gate::authorize('operate');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        Gate::authorize('manage');
        $this->resetForm();
        $this->loadStoreAssignments();
        $this->formOpen = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage');
        $definition = CheckDefinition::with('assignments')->findOrFail($id);

        $this->editingId = $definition->id;
        $this->name = $definition->name;
        $this->level = $definition->level;
        $this->executorType = $definition->executor_type;
        $this->configurationJson = $this->encodeJson($definition->configuration);
        $this->assertionsJson = $this->encodeJson($definition->assertions);
        $this->loadStoreAssignments($definition);
        $this->resetErrorBag();
        $this->formOpen = true;
    }

    public function closeForm(): void
    {
        $this->formOpen = false;
        $this->resetErrorBag();
    }

    public function save(CheckDefinitionService $service): void
    {
        Gate::authorize('manage');

        $definition = $this->editingId
            ? CheckDefinition::findOrFail($this->editingId)
            : null;

        $saved = $service->save(
            $definition,
            [
                'name' => $this->name,
                'level' => $this->level,
                'executor_type' => $this->executorType,
                'configuration' => $this->decodeJson('configurationJson'),
                'assertions' => $this->decodeJson('assertionsJson'),
            ],
            $this->selectedAssignments()
        );

        $this->editingId = $saved->id;
        $this->formOpen = false;
        session()->flash('status', 'Definição e associações salvas.');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->level = 'SITE';
        $this->executorType = 'http';
        $this->configurationJson = "{\n    \"path\": \"/\",\n    \"method\": \"GET\",\n    \"timeout\": 10,\n    \"follow_redirects\": false\n}";
        $this->assertionsJson = "[\n    {\n        \"field\": \"status_code\",\n        \"operator\": \"equals\",\n        \"expected\": 200\n    }\n]";
        $this->storeAssignments = [];
        $this->resetErrorBag();
    }

    private function loadStoreAssignments(?CheckDefinition $definition = null): void
    {
        $existing = $definition?->assignments->keyBy('store_id') ?? collect();

        $this->storeAssignments = Store::orderBy('name')->get(['id'])->mapWithKeys(function (Store $store) use ($existing): array {
            $assignment = $existing->get($store->id);

            return [(string) $store->id => [
                'selected' => $assignment !== null,
                'interval_minutes' => $assignment?->interval_minutes ?? 5,
                'enabled' => $assignment?->enabled ?? true,
            ]];
        })->all();
    }

    private function selectedAssignments(): array
    {
        return collect($this->storeAssignments)
            ->filter(fn (array $assignment) => (bool) ($assignment['selected'] ?? false))
            ->map(fn (array $assignment, string|int $storeId) => [
                'store_id' => (int) $storeId,
                'interval_minutes' => $assignment['interval_minutes'] ?? null,
                'enabled' => (bool) ($assignment['enabled'] ?? false),
            ])
            ->values()
            ->all();
    }

    private function decodeJson(string $property): array
    {
        try {
            $value = json_decode($this->{$property}, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([$property => 'Informe um JSON válido.']);
        }

        if (! is_array($value)) {
            throw ValidationException::withMessages([$property => 'O JSON deve representar um objeto ou uma lista.']);
        }

        return $value;
    }

    private function encodeJson(array $value): string
    {
        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function render()
    {
        $definitions = CheckDefinition::query()
            ->with(['assignments.store'])
            ->withCount('assignments')
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->latest()
            ->paginate(15);

        return view('livewire.stores.check-definitions', [
            'definitions' => $definitions,
            'stores' => Store::orderBy('name')->get(['id', 'name']),
            'levels' => CheckDefinitionValidator::LEVELS,
            'executorTypes' => CheckDefinitionValidator::EXECUTOR_TYPES,
        ])->layout('layouts.app', ['title' => 'Verificações 2']);
    }
}
