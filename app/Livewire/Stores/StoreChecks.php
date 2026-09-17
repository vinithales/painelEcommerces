<?php

namespace App\Livewire\Stores;

use App\Jobs\TestCheckJob;
use App\Models\AuditEvent;
use App\Models\Store;
use App\Models\StoreCheck;
use App\Services\Checks\CheckConfiguration;
use App\Services\Checks\CheckDispatcher;
use App\Support\OperationsQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class StoreChecks extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $storeId = '';

    #[Url]
    public string $levelFilter = '';

    public bool $wizard = false;

    public int $step = 1;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $selectedId = null;

    #[Locked]
    public ?string $testKey = null;

    public string $name = '';

    public string $targetStore = '';

    public string $level = 'SITE';

    public string $checkType = 'HTTP_STATUS';

    public array $configuration = ['url' => '/', 'method' => 'GET', 'expected_status' => 200, 'timeout' => 10, 'follow_redirects' => false];

    public int $interval = 300;

    public int $failures = 2;

    public int $cooldown = 1800;

    public string $severity = 'CRITICAL';

    public bool $notifyFailure = true;

    public bool $notifyRecovery = true;

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

    public function updatedLevelFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        Gate::authorize('manage');
        $this->reset(['editingId', 'testKey', 'name', 'level', 'checkType', 'configuration', 'interval', 'failures', 'cooldown', 'severity', 'notifyFailure', 'notifyRecovery']);
        $this->targetStore = $this->storeId;
        $this->step = 1;
        $this->wizard = true;
        $this->selectedId = null;
        $this->resetErrorBag();
    }

    public function updatedCheckType(): void
    {
        $this->configuration = CheckConfiguration::defaults($this->checkType);
        $this->testKey = null;
    }

    private function definition(): array
    {
        $this->validate(['name' => 'required|string|max:255', 'targetStore' => 'required|exists:stores,id', 'level' => 'required|in:SERVER,APP,API,SITE', 'checkType' => ['required', Rule::in(CheckConfiguration::TYPES)], 'interval' => ['required', 'integer', Rule::in(CheckConfiguration::INTERVALS)], 'failures' => 'required|integer|min:1|max:100', 'cooldown' => 'required|integer|min:0|max:86400', 'severity' => 'required|in:WARNING,CRITICAL']);
        $config = app(CheckConfiguration::class)->validate($this->checkType, $this->configuration);
        if ($this->checkType === 'MAGENTO_CRON' && ! Store::findOrFail($this->targetStore)->isMagento2()) {
            throw ValidationException::withMessages(['targetStore' => 'Cron exige Magento 2.']);
        }

        return ['name' => $this->name, 'store_id' => (int) $this->targetStore, 'level' => $this->level, 'check_type' => $this->checkType, 'configuration' => $config, 'interval_seconds' => $this->interval, 'consecutive_failures_before_alert' => $this->failures, 'cooldown_seconds' => $this->cooldown, 'severity' => $this->severity, 'notify_on_failure' => $this->notifyFailure, 'notify_on_recovery' => $this->notifyRecovery];
    }

    public function nextStep(): void
    {
        Gate::authorize('manage');
        if ($this->step === 1) {
            $this->validate(['name' => 'required|max:255', 'targetStore' => 'required|exists:stores,id', 'level' => 'in:SERVER,APP,API,SITE', 'checkType' => Rule::in(CheckConfiguration::TYPES)]);
        }
        if ($this->step === 2) {
            app(CheckConfiguration::class)->validate($this->checkType, $this->configuration);
        }
        $this->step = min(5, $this->step + 1);
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function testCheck(): void
    {
        Gate::authorize('manage');
        $definition = $this->definition();
        $this->testKey = 'check-test:'.auth()->id().':'.Str::uuid();
        Cache::put($this->testKey, ['status' => 'QUEUED', 'summary' => 'Teste aguardando worker.'], 600);
        TestCheckJob::dispatch($definition, $this->testKey)->onConnection(OperationsQueue::connection());
        AuditEvent::record((int) $this->targetStore, 'check', 'test', ['check_type' => $this->checkType]);
    }

    public function save(): void
    {
        Gate::authorize('manage');
        $definition = $this->definition();
        if ($this->editingId) {
            $check = StoreCheck::findOrFail($this->editingId);
            $check->update($definition + ['next_run_at' => now()]);
        } else {
            $check = StoreCheck::create($definition + ['created_by' => auth()->id(), 'next_run_at' => now()]);
        }
        AuditEvent::record($check->store_id, 'check', $this->editingId ? 'update' : 'create', ['check_id' => $check->id]);
        $this->wizard = false;
        $this->selectedId = $check->id;
        session()->flash('status', 'Verificação salva.');
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage');
        $c = StoreCheck::findOrFail($id);
        $this->editingId = $id;
        $this->selectedId = null;
        $this->name = $c->name;
        $this->targetStore = (string) $c->store_id;
        $this->level = $c->level;
        $this->checkType = $c->check_type;
        $this->configuration = $c->configuration;
        $this->interval = $c->interval_seconds;
        $this->failures = $c->consecutive_failures_before_alert;
        $this->cooldown = $c->cooldown_seconds;
        $this->severity = $c->severity;
        $this->notifyFailure = $c->notify_on_failure;
        $this->notifyRecovery = $c->notify_on_recovery;
        $this->step = 1;
        $this->wizard = true;
        $this->testKey = null;
    }

    public function open(int $id): void
    {
        $this->selectedId = StoreCheck::findOrFail($id)->id;
    }

    public function closeSelected(): void
    {
        $this->selectedId = null;
    }

    public function runNow(int $id): void
    {
        Gate::authorize('operate');
        $ok = app(CheckDispatcher::class)->dispatch(StoreCheck::findOrFail($id), true, auth()->id());
        session()->flash('status', $ok ? 'Execução enfileirada.' : 'Verificação desabilitada ou já aguardando execução.');
    }

    public function toggle(int $id): void
    {
        Gate::authorize('manage');
        $check = StoreCheck::findOrFail($id);
        $check->update(['enabled' => ! $check->enabled, 'next_run_at' => now()]);
        AuditEvent::record($check->store_id, 'check', 'toggle', ['check_id' => $id, 'enabled' => $check->enabled]);
    }

    public function render()
    {
        $checks = StoreCheck::with('store')->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))->when($this->storeId, fn ($q) => $q->where('store_id', $this->storeId))->when($this->levelFilter, fn ($q) => $q->where('level', $this->levelFilter))->latest()->paginate(15);
        $selected = $this->selectedId ? StoreCheck::with(['store', 'runs' => fn ($q) => $q->latest()->limit(30), 'alerts' => fn ($q) => $q->latest()->limit(10)])->find($this->selectedId) : null;

        return view('livewire.stores.store-checks', ['checks' => $checks, 'stores' => Store::orderBy('name')->get(['id', 'name']), 'selected' => $selected, 'testResult' => $this->testKey ? Cache::get($this->testKey) : null])->layout('layouts.app', ['title' => 'Verificações']);
    }
}
