<?php

namespace App\Livewire\Stores;

use App\Jobs\SupportOperationJob;
use App\Models\AuditEvent;
use App\Models\Store;
use App\Services\Checks\PublicHttpClient;
use App\Services\Infrastructure\AgentServerConnection;
use App\Support\OperationsQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StoreDiagnostics extends Component
{
    public Store $store;

    public array $ssh = ['host' => '', 'port' => 22, 'user' => '', 'host_key' => '', 'private_key' => ''];

    public string $endpoint = '';

    public string $token = '';

    public string $logFile = 'system.log';

    public string $search = '';

    public string $logLevel = '';

    #[Locked]
    public ?string $taskKey = null;

    #[Locked]
    public string $operation = '';

    #[Locked]
    public string $logText = '';

    #[Locked]
    public ?int $cursor = null;

    #[Locked]
    public ?string $consumedKey = null;

    public function boot(): void
    {
        Gate::authorize('operate');
    }

    public function mount(Store $store): void
    {
        $this->store = $store;
        if (Gate::allows('configure')) {
            $this->ssh['host'] = $store->ssh_ip ?? '';
            $this->ssh['user'] = $store->ssh_user ?? '';
            $this->ssh = array_replace($this->ssh, array_diff_key($store->ssh_profile ?? [], ['private_key' => true]));
        }
        $this->endpoint = $store->serverConnection?->endpoint ?? '';
    }

    public function updatedLogFile(): void
    {
        $this->logText = '';
        $this->cursor = null;
        $this->taskKey = null;
    }

    public function saveSsh(): void
    {
        Gate::authorize('configure');
        $validated = $this->validate([
            'ssh' => 'required|array:host,port,user,host_key,private_key',
            'ssh.host' => ['required', 'string', 'max:253', filter_var($this->ssh['host'] ?? '', FILTER_VALIDATE_IP) ? 'ip' : 'regex:/^[a-z0-9][a-z0-9.-]*$/iD'],
            'ssh.port' => 'required|integer|between:1,65535',
            'ssh.user' => ['required', 'max:64', 'regex:/^[a-z_][a-z0-9_-]*$/iD'],
            'ssh.host_key' => ['required', 'max:4096', 'regex:~^(?:ssh-ed25519|ssh-rsa|ecdsa-sha2-nistp(?:256|384|521)) [A-Za-z0-9+/]+=*$~D'],
            'ssh.private_key' => ['nullable', 'string', 'max:16384'],
        ])['ssh'];
        $validated['private_key'] = ($validated['private_key'] ?? null) ?: ($this->store->ssh_profile['private_key'] ?? null);
        if (! $validated['private_key'] && ! $this->store->hasSshPasswordFor($validated['host'], $validated['user'])) {
            throw ValidationException::withMessages(['ssh.private_key' => 'Informe a chave privada ou cadastre uma senha SSH para este IP e usuário na edição da loja.']);
        }
        $this->store->ssh_profile = $validated;
        $this->store->save();
        $this->ssh['private_key'] = '';
        AuditEvent::record($this->store->id, 'server', 'configure', ['driver' => 'ssh']);
        session()->flash('status', 'Conexão SSH salva.');
    }

    public function saveConnection(): void
    {
        Gate::authorize('configure');
        $this->validate(['endpoint' => 'required|url:https|max:2048', 'token' => ($this->store->serverConnection ? 'nullable' : 'required').'|string|min:32|max:4096']);
        if (parse_url($this->endpoint, PHP_URL_QUERY) || parse_url($this->endpoint, PHP_URL_FRAGMENT)) {
            throw ValidationException::withMessages(['endpoint' => 'Use o endpoint sem query ou fragmento.']);
        }
        app(PublicHttpClient::class)->address($this->endpoint);
        $data = ['driver' => 'agent', 'endpoint' => $this->endpoint];
        if ($this->token !== '') {
            $data['token'] = $this->token;
        }
        $this->store->serverConnection()->updateOrCreate(['store_id' => $this->store->id], $data);
        $this->token = '';
        $this->store->unsetRelation('serverConnection');
        AuditEvent::record($this->store->id, 'server', 'configure', ['driver' => 'agent']);
        session()->flash('status', 'Conexão salva.');
    }

    public function execute(string $operation, bool $more = false): void
    {
        Gate::authorize('operate');
        abort_unless(in_array($operation, ['server_status', 'server_cron', 'server_logs'], true), 422);
        $this->validate(['logFile' => Rule::in(AgentServerConnection::LOGS)]);
        if (! $more) {
            $this->logText = '';
            $this->cursor = null;
        }
        $this->operation = $operation;
        $this->taskKey = 'support:'.auth()->id().':'.Str::uuid();
        Cache::put($this->taskKey, ['complete' => false], 600);
        SupportOperationJob::dispatch($this->store->id, $operation, ['file' => $this->logFile, 'cursor' => $more ? $this->cursor : null], $this->taskKey, auth()->id())->onConnection(OperationsQueue::connection());
    }

    public function pollTask(): void
    {
        if (! $this->taskKey || $this->consumedKey === $this->taskKey) {
            return;
        }
        $task = Cache::get($this->taskKey);
        if (($task['complete'] ?? false) && $this->operation === 'server_logs' && isset($task['result']['text'])) {
            $this->logText = substr($task['result']['text'].$this->logText, -config('operations.log_max_bytes'));
            $this->cursor = $task['result']['cursor'];
            $this->consumedKey = $this->taskKey;
        }
    }

    public function filteredLog(): string
    {
        $entries = preg_split('/(?=^\[?\d{4}-\d{2}-\d{2})/m', $this->logText, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode('', array_filter($entries, fn ($entry) => ($this->search === '' || stripos($entry, $this->search) !== false) && ($this->logLevel === '' || stripos($entry, $this->logLevel) !== false)));
    }

    public function download()
    {
        Gate::authorize('operate');
        AuditEvent::record($this->store->id, 'server_log', 'download', ['file' => $this->logFile]);
        $text = $this->filteredLog();

        return response()->streamDownload(fn () => print ($text), 'trecho-'.$this->logFile, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function render()
    {
        return view('livewire.stores.store-diagnostics', ['task' => $this->taskKey ? Cache::get($this->taskKey) : null, 'visibleLog' => $this->filteredLog()])->layout('layouts.app', ['store' => $this->store, 'title' => 'Diagnóstico']);
    }
}
