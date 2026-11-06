<?php

namespace App\Livewire\Stores;

use App\Actions\Checks\CheckDefinitionValidator;
use App\Jobs\RunCheckAssignmentJob;
use App\Models\CheckAssignment;
use App\Models\CheckDefinition;
use App\Models\CheckExecution;
use App\Models\Store;
use App\Services\Checks\CheckDefinitionService;
use App\Services\Checks\CheckExecutionFeedback;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CheckDefinitions extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $storeId = null;

    public bool $formOpen = false;

    #[Locked]
    public int $formStep = 1;

    public const FORM_STEPS = [
        1 => ['title' => 'Definição', 'description' => 'Nome e tipo da verificação'],
        2 => ['title' => 'Configuração', 'description' => 'Página e forma de acesso'],
        3 => ['title' => 'Condições', 'description' => 'Critérios de sucesso'],
        4 => ['title' => 'Lojas e conclusão', 'description' => 'Destino e frequência'],
    ];

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $level = 'SITE';

    public string $executorType = 'http';

    public string $configurationJson = "{\n    \"path\": \"/\",\n    \"method\": \"GET\",\n    \"timeout\": 10,\n    \"follow_redirects\": false\n}";

    public array $assertions = [['field' => 'status_code', 'operator' => 'equals', 'expected' => 200]];

    public array $httpConfiguration = ['path' => '/', 'method' => 'GET', 'timeout' => 10, 'follow_redirects' => false];

    public array $browserConfiguration = ['path' => '/', 'timeout' => 30, 'wait_ms' => 5000];

    public array $apiConfiguration = ['path' => '/', 'method' => 'GET', 'timeout' => 10, 'authenticate' => false, 'payload' => '', 'json_path' => '', 'header_lines' => ''];

    public array $sshConfiguration = ['script' => '', 'timeout' => 30];

    public string $scriptSource = '';

    public const SCRIPT_MODELS = [
        'disco' => ['Uso de disco', 'df -hP /'],
        'memoria' => ['Memória disponível (Linux)', 'free -m'],
        'servico' => ['Estado de um serviço (systemd)', 'systemctl is-active nginx'],
        'magento-indices' => ['Magento 2 — indexadores possivelmente travados', null, 'magento-indices.sh'],
    ];

    public const FIELD_LABELS = [
        'json_number' => 'Valor numérico do campo JSON',
        'exit_code' => 'Código de saída do script',
        'stdout' => 'Saída do script',
        'stderr' => 'Saída de erro do script',
        'status_code' => 'Código de resposta HTTP',
        'response_time' => 'Tempo de resposta',
        'url' => 'Endereço da página',
        'console_error_count' => 'Quantidade de erros de console',
        'page_error_count' => 'Quantidade de exceções JavaScript',
        'headers.x-cache' => 'Cabeçalho X-Cache',
        'headers.x-magento-cache-debug' => 'Cabeçalho X-Magento-Cache-Debug',
        'cache_status' => 'Estado do cache (detecção automática)',
        'headers.cf-cache-status' => 'Cabeçalho CF-Cache-Status',
        'headers.x-litespeed-cache' => 'Cabeçalho X-LiteSpeed-Cache',
        'headers.cache-control' => 'Cabeçalho Cache-Control',
        'headers.age' => 'Cabeçalho Age',
        'body' => 'Conteúdo da resposta',
    ];

    public const OPERATOR_LABELS = [
        'contains_ignore_case' => 'Contém (ignorar maiúsculas)',
        'not_contains_ignore_case' => 'Não contém (ignorar maiúsculas)',
        'equals' => 'Igual a',
        'not_equals' => 'Diferente de',
        'greater_than' => 'Maior que',
        'less_than' => 'Menor que',
        'greater_than_or_equal' => 'Maior ou igual a',
        'less_than_or_equal' => 'Menor ou igual a',
        'contains' => 'Contém',
        'not_contains' => 'Não contém',
    ];

    public array $storeAssignments = [];

    #[Locked]
    public ?int $executionDefinitionId = null;

    public string $executionScope = 'all';

    public ?int $executionAssignmentId = null;

    public array $executionAssignmentIds = [];

    #[Locked]
    public array $executionIds = [];

    #[Locked]
    public array $executionProgress = [];

    #[Locked]
    public bool $executionStarted = false;

    #[Locked]
    public ?string $executionStatus = null;

    public function openExecution(int $id): void
    {
        Gate::authorize('operate');
        $definition = CheckDefinition::with('assignments')->findOrFail($id);
        $this->closeForm();
        $this->resetExecution();
        $this->executionStatus = null;
        $this->executionDefinitionId = $definition->id;
        $enabledAssignments = $definition->assignments->where('enabled', true);
        $storeAssignment = $this->storeId ? $enabledAssignments->firstWhere('store_id', $this->storeId) : null;
        $this->executionScope = $storeAssignment || $enabledAssignments->count() === 1 ? 'single' : 'all';
        $this->executionAssignmentId = ($storeAssignment ?? $enabledAssignments->first())?->id;
    }

    public function closeExecution(): void
    {
        $this->executionDefinitionId = null;
        $this->executionAssignmentId = null;
        $this->executionScope = 'all';
        $this->resetExecution();
        $this->resetErrorBag();
    }

    private function resetExecution(): void
    {
        $this->executionAssignmentIds = [];
        $this->executionIds = [];
        $this->executionProgress = [];
        $this->executionStarted = false;
    }

    public function executeSelected(): void
    {
        Gate::authorize('operate');
        if ($this->executionStarted) {
            return;
        }
        abort_if($this->executionDefinitionId === null, 404);
        $definition = CheckDefinition::findOrFail($this->executionDefinitionId);
        $this->validate(['executionScope' => 'required|in:all,single,group']);

        if (! in_array($definition->executor_type, CheckDefinitionValidator::RUNNABLE, true)) {
            throw ValidationException::withMessages(['executionScope' => 'Executor indisponível']);
        }

        $assignments = $definition->assignments()->with(['store', 'checkDefinition'])->where('enabled', true);

        if ($this->executionScope === 'single') {
            $this->validate(['executionAssignmentId' => 'required|integer']);
            if (! (clone $assignments)->whereKey($this->executionAssignmentId)->exists()) {
                throw ValidationException::withMessages(['executionAssignmentId' => 'Selecione uma loja habilitada desta verificação.']);
            }
            $assignments->whereKey($this->executionAssignmentId);
        } elseif ($this->executionScope === 'group') {
            $this->validate([
                'executionAssignmentIds' => 'required|array|min:1',
                'executionAssignmentIds.*' => 'required|integer|distinct',
            ], ['executionAssignmentIds.required' => 'Selecione pelo menos uma loja.']);
            $selectedIds = array_map('intval', $this->executionAssignmentIds);
            $assignments->whereKey($selectedIds);
            if ((clone $assignments)->count() !== count($selectedIds)) {
                throw ValidationException::withMessages(['executionAssignmentIds' => 'Selecione apenas lojas habilitadas desta verificação.']);
            }
        }

        $selected = $assignments->get();
        if ($selected->isEmpty()) {
            throw ValidationException::withMessages(['executionScope' => 'Nenhuma loja habilitada para executar esta verificação.']);
        }

        $this->executionStarted = true;
        foreach ($selected as $assignment) {
            $this->enqueueAssignment($assignment);
        }
        $this->executionStatus = $this->executionScope === 'single'
            ? 'Execução agendada para '.$selected->first()->store->name.'.'
            : 'Execução agendada para '.$selected->count().($selected->count() === 1 ? ' loja habilitada.' : ' lojas habilitadas.');
        if (collect($this->executionProgress)->contains('status', 'error')) {
            $this->executionStatus = 'Uma ou mais execuções não puderam ser agendadas. Confira os detalhes no painel.';
        }
        $this->refreshExecutionProgress();
    }

    private function enqueueAssignment(CheckAssignment $assignment): void
    {
        $key = 'assignment-'.$assignment->id;
        try {
            $execution = $assignment->executions()->create([
                'definition_snapshot' => $assignment->checkDefinition->only(['name', 'level', 'executor_type', 'configuration', 'assertions']),
                'status' => 'pending',
            ]);
            $key = (string) $execution->id;
            $this->executionIds[] = $execution->id;
            $this->executionProgress[$key] = ['store' => $assignment->store->name, 'store_id' => $assignment->store_id, 'status' => 'pending', 'message' => null];
            try {
                RunCheckAssignmentJob::dispatch($assignment->id, $execution->id);
            } catch (\Throwable $e) {
                $execution->update(['status' => 'error', 'error_message' => 'Não foi possível enviar a execução para a fila.', 'finished_at' => now()]);
                throw $e;
            }
        } catch (\Throwable $e) {
            report($e);
            $this->executionProgress[$key] = [
                'store' => $assignment->store->name,
                'status' => 'error',
                'message' => 'Não foi possível agendar a execução. Tente novamente.',
            ];
        }
    }

    public function refreshExecutionProgress(): void
    {
        Gate::authorize('operate');
        if (! $this->executionStarted || $this->executionIds === []) {
            return;
        }
        $executions = CheckExecution::whereIn('id', $this->executionIds)->get()->keyBy('id');
        foreach ($this->executionIds as $id) {
            $execution = $executions->get($id);
            $status = $execution?->status ?? 'error';
            $message = $execution?->error_message;
            if (! $execution) {
                $message = 'O registro desta execução não está mais disponível.';
            } elseif ($status === 'completed') {
                $status = in_array($execution->outcome, ['success', 'passed'], true) ? 'success' : 'failed';
                if ($status === 'failed') {
                    $message = 'Uma ou mais condições da verificação não foram atendidas.';
                }
                $observed = $execution->result['observed'] ?? [];
                if (isset($observed['console_error_count'], $observed['page_error_count'])) {
                    $message = $observed['console_error_count'].' erro(s) de console; '.$observed['page_error_count'].' exceção(ões) JavaScript.';
                } elseif (isset($observed['cache_status'])) {
                    $feedback = app(CheckExecutionFeedback::class)->cache($observed);
                    $message = $feedback['title'].'. '.$feedback['explanation'];
                }
            }
            $this->executionProgress[(string) $id]['status'] = $status;
            $this->executionProgress[(string) $id]['message'] = $message;
        }
    }

    public function boot(): void
    {
        Gate::authorize('operate');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStoreId(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        Gate::authorize('manage');
        $this->resetForm();
        $this->closeExecution();
        $this->loadStoreAssignments();
        $this->formOpen = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage');
        $this->closeExecution();
        $definition = CheckDefinition::with('assignments')->findOrFail($id);

        $this->editingId = $definition->id;
        $this->formStep = 1;
        $this->name = $definition->name;
        $this->level = $definition->level;
        $this->executorType = $definition->executor_type;
        $this->configurationJson = $this->encodeJson($definition->configuration);
        $this->reset('apiConfiguration', 'sshConfiguration', 'httpConfiguration', 'scriptSource');
        if ($definition->executor_type === 'api') {
            $this->apiConfiguration = array_replace($this->apiConfiguration, array_intersect_key($definition->configuration, $this->apiConfiguration));
        } elseif ($definition->executor_type === 'ssh') {
            $this->sshConfiguration = $definition->configuration;
        } elseif ($definition->executor_type === 'http') {
            $this->httpConfiguration = $definition->configuration;
        }
        $this->browserConfiguration = $definition->executor_type === 'browser' ? $definition->configuration : ['path' => '/', 'timeout' => 30, 'wait_ms' => 5000];
        $this->assertions = $definition->assertions;
        $this->loadStoreAssignments($definition);
        $this->resetErrorBag();
        $this->formOpen = true;
    }

    public function closeForm(): void
    {
        $this->formOpen = false;
        $this->resetErrorBag();
    }

    public function previousStep(): void
    {
        Gate::authorize('manage');
        $this->formStep = max(1, $this->formStep - 1);
        $this->resetErrorBag();
    }

    public function goToStep(int $step, CheckDefinitionValidator $validator): void
    {
        Gate::authorize('manage');

        if (! array_key_exists($step, self::FORM_STEPS)) {
            return;
        }

        if ($step <= $this->formStep) {
            $this->formStep = $step;
            $this->resetErrorBag();

            return;
        }

        while ($this->formStep < $step) {
            $this->nextStep($validator);
        }
    }

    public function nextStep(CheckDefinitionValidator $validator): void
    {
        Gate::authorize('manage');
        if ($this->formStep === 1) {
            $this->validate([
                'name' => 'required|string|max:255',
                'level' => ['required', Rule::in(CheckDefinitionValidator::LEVELS)],
                'executorType' => ['required', Rule::in(CheckDefinitionValidator::EXECUTOR_TYPES)],
            ], ['name.required' => 'Dê um nome para esta verificação.']);
        } elseif (in_array($this->formStep, [2, 3], true)) {
            // Validate the current step with the same rules used when saving.
            $validator->validate([
                'name' => $this->name,
                'level' => $this->level,
                'executor_type' => $this->executorType,
                'configuration' => $this->configuration(),
                'assertions' => $this->formStep === 3 ? $this->normalizedAssertions()
                    : [['field' => 'response_time', 'operator' => 'less_than', 'expected' => 3000]],
            ]);
        }

        $this->formStep = min(4, $this->formStep + 1);
        $this->resetErrorBag();
    }

    public function save(CheckDefinitionService $service): void
    {
        Gate::authorize('manage');

        $definition = $this->editingId
            ? CheckDefinition::findOrFail($this->editingId)
            : null;

        try {
            $saved = $service->save(
                $definition,
                [
                    'name' => $this->name,
                    'level' => $this->level,
                    'executor_type' => $this->executorType,
                    'configuration' => $this->configuration(),
                    'assertions' => $this->normalizedAssertions(),
                ],
                $this->selectedAssignments()
            );
        } catch (ValidationException $exception) {
            $keys = array_keys($exception->errors());
            $this->formStep = match (true) {
                collect($keys)->contains(fn ($key) => in_array($key, ['name', 'level', 'executor_type'], true)) => 1,
                collect($keys)->contains(fn ($key) => str_starts_with($key, 'configuration')) => 2,
                collect($keys)->contains(fn ($key) => str_starts_with($key, 'assertions')) => 3,
                default => 4,
            };
            throw $exception;
        }

        $this->editingId = $saved->id;
        $this->formOpen = false;
        session()->flash('status', 'Definição e associações salvas.');
    }

    public function delete(int $id, CheckDefinitionService $service): void
    {
        Gate::authorize('manage');
        $definition = CheckDefinition::findOrFail($id);
        $service->delete($definition);
        session()->flash('status', 'Definição removida.');
    }

    public function disable(int $id): void
    {
        Gate::authorize('manage');
        $definition = CheckDefinition::findOrFail($id);
        $definition->assignments()->update(['enabled' => false, 'next_run_at' => null]);
        session()->flash('status', 'Todas as associações de '.$definition->name.' foram desabilitadas.');
    }

    public function runNow(int $id): void
    {
        Gate::authorize('operate');
        $assignment = CheckAssignment::with(['checkDefinition', 'store'])->findOrFail($id);
        if (! in_array($assignment->checkDefinition->executor_type, CheckDefinitionValidator::RUNNABLE, true)) {
            session()->flash('status', 'Executor indisponível');

            return;
        }
        if (! $assignment->enabled) {
            session()->flash('status', 'Nenhuma verificação habilitada para execução imediata.');

            return;
        }

        $this->executionStarted = true;
        $this->enqueueAssignment($assignment);
        $this->refreshExecutionProgress();
        $this->executionStatus = 'Execução agendada para '.$assignment->store->name.'.';
        if (collect($this->executionProgress)->contains('status', 'error')) {
            $this->executionStatus = 'Não foi possível agendar a execução. Confira os detalhes no painel.';
        }
        session()->flash('status', $this->executionStatus);
    }

    private function resetForm(): void
    {
        $this->formStep = 1;
        $this->reset('apiConfiguration', 'sshConfiguration', 'scriptSource');
        $this->editingId = null;
        $this->name = '';
        $this->level = 'SITE';
        $this->executorType = 'http';
        $this->configurationJson = "{\n    \"path\": \"/\",\n    \"method\": \"GET\",\n    \"timeout\": 10,\n    \"follow_redirects\": false\n}";
        $this->httpConfiguration = ['path' => '/', 'method' => 'GET', 'timeout' => 10, 'follow_redirects' => false];
        $this->browserConfiguration = ['path' => '/', 'timeout' => 30, 'wait_ms' => 5000];
        $this->assertions = [['field' => 'status_code', 'operator' => 'equals', 'expected' => 200]];
        $this->storeAssignments = [];
        $this->resetErrorBag();
    }

    public function addAssertion(): void
    {
        Gate::authorize('manage');
        $this->assertions[] = ['field' => 'response_time', 'operator' => 'less_than', 'expected' => 3000];
        $this->resetErrorBag();
    }

    public function removeAssertion(int $index): void
    {
        Gate::authorize('manage');
        if (count($this->assertions) > 1) {
            unset($this->assertions[$index]);
            $this->assertions = array_values($this->assertions);
            $this->resetErrorBag();
        }
    }

    public function updatedAssertions(mixed $value, ?string $key): void
    {
        if ($key !== null && preg_match('/^(\d+)\.field$/', $key, $matches)) {
            $index = (int) $matches[1];
            $this->assertions[$index]['operator'] = 'equals';
            $this->assertions[$index]['expected'] = '';
            $this->resetErrorBag();
        }
    }

    private function configuration(): array
    {
        return match ($this->executorType) {
            'api' => $this->apiConfiguration,
            'ssh' => $this->sshConfiguration,
            'http' => $this->normalizedHttpConfiguration(),
            'browser' => $this->normalizedBrowserConfiguration(),
            default => $this->decodeJson('configurationJson'),
        };
    }

    public function updatedExecutorType(string $type): void
    {
        $this->level = match ($type) {
            'ssh' => 'SERVER', 'api' => 'API', default => $this->level,
        };
        $this->assertions = [[
            'field' => $type === 'ssh' ? 'exit_code' : 'status_code',
            'operator' => 'equals', 'expected' => $type === 'ssh' ? 0 : 200,
        ]];
    }

    public function useScript(): void
    {
        Gate::authorize('manage');
        if (isset(self::SCRIPT_MODELS[$this->scriptSource])) {
            $model = self::SCRIPT_MODELS[$this->scriptSource];
            $this->sshConfiguration['script'] = $model[1] ?? \Illuminate\Support\Facades\File::get(resource_path('scripts/'.$model[2]));
        } elseif (ctype_digit($this->scriptSource)) {
            $this->sshConfiguration['script'] = CheckDefinition::where('executor_type', 'ssh')
                ->findOrFail($this->scriptSource)->configuration['script'];
        }
    }

    private function normalizedAssertions(): array
    {
        $this->validate([
            'assertions' => 'required|array|min:1',
            'assertions.*' => 'array:field,operator,expected',
            'assertions.*.field' => 'required|string',
            'assertions.*.operator' => 'required|string',
            'assertions.*.expected' => 'present',
        ]);

        return array_map(function (array $assertion): array {
            if ($assertion['field'] === 'json_number' && is_numeric($assertion['expected'])) {
                $assertion['expected'] += 0;
            }
            if (in_array($assertion['field'], ['exit_code', 'status_code', 'response_time', 'console_error_count', 'page_error_count'], true)) {
                // Convert only valid integer input; leave invalid values for backend validation.
                $value = $assertion['expected'];
                if (is_string($value) && preg_match('/^\d+$/D', $value)
                    && filter_var($value, FILTER_VALIDATE_INT) !== false) {
                    $assertion['expected'] = (int) $value;
                }
            }

            return $assertion;
        }, $this->assertions);
    }

    private function normalizedBrowserConfiguration(): array
    {
        $configuration = $this->browserConfiguration;
        foreach (['timeout', 'wait_ms'] as $key) {
            if (isset($configuration[$key]) && is_string($configuration[$key]) && ctype_digit($configuration[$key])) {
                $configuration[$key] = (int) $configuration[$key];
            }
        }

        return $configuration;
    }

    private function normalizedHttpConfiguration(): array
    {
        $configuration = $this->httpConfiguration;
        $timeout = $configuration['timeout'] ?? null;
        if (is_string($timeout) && preg_match('/^\d+$/D', $timeout)
            && filter_var($timeout, FILTER_VALIDATE_INT) !== false) {
            $configuration['timeout'] = (int) $timeout;
        }

        foreach (['samples'] as $key) {
            if (isset($configuration[$key]) && is_string($configuration[$key]) && ctype_digit($configuration[$key])) {
                $configuration[$key] = (int) $configuration[$key];
            }
        }

        return $configuration;
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
            ->with([
                'assignments.store',
                'assignments.latestExecution' => fn ($query) => $query->select('check_executions.id', 'check_executions.check_assignment_id', 'status', 'outcome', 'started_at'),
                'assignments.latestStartedExecution' => fn ($query) => $query->select('check_executions.id', 'check_executions.check_assignment_id', 'started_at'),
            ])
            ->withCount('assignments')
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->storeId, fn ($query) => $query->whereHas('assignments', fn ($assignment) => $assignment->where('store_id', $this->storeId)))
            ->latest()
            ->paginate(15);

        return view('livewire.stores.check-definitions', [
            'definitions' => $definitions,
            'executionDefinition' => $this->executionDefinitionId
                ? CheckDefinition::with('assignments.store')->find($this->executionDefinitionId)
                : null,
            'stores' => Store::orderBy('name')->get(['id', 'name']),
            'levels' => CheckDefinitionValidator::LEVELS,
            'scriptLibrary' => $this->formOpen && $this->formStep === 2 && $this->executorType === 'ssh' && Gate::allows('manage')
                ? CheckDefinition::where('executor_type', 'ssh')->orderBy('name')->pluck('name', 'id') : [],
            'fieldLabels' => array_filter(self::FIELD_LABELS, fn ($field) => match ($this->executorType) {
                'ssh' => in_array($field, CheckDefinitionValidator::SSH_FIELDS, true),
                default => ! in_array($field, ['stdout', 'stderr', 'exit_code'], true)
                    && ($field !== 'json_number' || ($this->executorType === 'api' && filled($this->apiConfiguration['json_path'] ?? null)))
                    && ($field !== 'cache_status' || $this->executorType === 'http')
                    && (! in_array($field, ['console_error_count', 'page_error_count'], true) || $this->executorType === 'browser'),
            }, ARRAY_FILTER_USE_KEY),
            'executorTypes' => CheckDefinitionValidator::EXECUTOR_TYPES,
        ])->layout('layouts.app', ['title' => 'Verificação']);
    }
}
