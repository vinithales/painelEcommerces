<div class="stack-xl" wire:poll.15s>
    <div class="page-header">
        <div>
            <h2>Relatórios de verificações</h2>
            <p>O resultado mais recente de cada verificação por loja, incluindo associações desabilitadas.</p>
        </div>
        <a class="btn btn-secondary" wire:navigate href="{{ route('stores.checks') }}">Gerenciar verificações</a>
    </div>

    <div class="toolbar">
        <label>Verificação
            <select wire:model.live="definitionId">
                <option value="">Todas as verificações</option>
                @foreach($definitions as $definition)<option value="{{ $definition->id }}">{{ $definition->name }}</option>@endforeach
            </select>
        </label>
        <label>Loja
            <select wire:model.live="storeId">
                <option value="">Todas as lojas</option>
                @foreach($stores as $store)<option value="{{ $store->id }}">{{ $store->name }}</option>@endforeach
            </select>
        </label>
        <label>Resultado
            <select wire:model.live="status">
                @foreach(\App\Livewire\Stores\CheckReports::STATUS_LABELS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        </label>
    </div>

    <div class="stats-grid check-report__metrics" aria-label="Resumo das lojas e verificações selecionadas">
        @foreach(['all' => 'Lojas × verificações', 'attention' => 'Falhas e erros', 'success' => 'Sucesso', 'active' => 'Na fila / executando', 'never' => 'Sem execução'] as $value => $label)
            <button type="button" class="stat-card check-report__metric {{ $status === $value ? 'is-selected' : '' }}" wire:click="$set('status', '{{ $value }}')" aria-pressed="{{ $status === $value ? 'true' : 'false' }}">
                <span class="stat-card__label">{{ $label }}</span>
                <strong class="stat-card__value">{{ $counts[$value] }}</strong>
                <span class="stat-card__desc">{{ $value === 'attention' ? 'Ver lojas que precisam de atenção' : 'Filtrar resultados' }}</span>
            </button>
        @endforeach
    </div>

    <p class="muted">Última execução iniciada nas lojas selecionadas:
        @if($lastRunAt)
            <time datetime="{{ \Illuminate\Support\Carbon::parse($lastRunAt)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($lastRunAt)->format('d/m/Y H:i:s') }}</time>
        @else
            Nenhuma execução iniciada.
        @endif
        · Atualização automática a cada 15 segundos. Horários: {{ config('app.timezone') }}.
    </p>

    <div class="card table-wrap">
        <table class="data-table check-report__table">
            <caption class="check-report__caption">Último resultado por loja e verificação</caption>
            <thead><tr><th scope="col">Loja / verificação</th><th scope="col">Resultado mais recente</th><th scope="col">Última vez que rodou</th><th scope="col">Condições e resultado observado</th><th scope="col">Próxima execução</th><th scope="col">Detalhes</th></tr></thead>
            <tbody>
                @forelse($assignments as $assignment)
                    @php
                        $execution = $assignment->latestExecution;
                        $state = $execution?->reportStatus() ?? 'never';
                        $label = \App\Livewire\Stores\CheckReports::STATUS_LABELS[$state] ?? 'Sem resultado';
                        $style = in_array($state, ['failed', 'error'], true) ? 'fail' : (in_array($state, ['success', 'running'], true) ? $state : 'pending');
                        $lastStarted = $assignment->latestStartedExecution;
                        $observed = $execution?->result['observed'] ?? [];
                        $evaluations = collect($execution?->result['assertions'] ?? []);
                        $failedConditions = $evaluations->filter(fn ($condition) => ($condition['passed'] ?? null) === false);
                        $formatValue = static fn ($value) => $value === null ? 'Não informado' : \Illuminate\Support\Str::limit(is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 180);
                    @endphp
                    <tr wire:key="report-assignment-{{ $assignment->id }}">
                        <td><strong>{{ $assignment->store->name }}</strong><p>{{ $assignment->checkDefinition->name }}</p>@unless($assignment->enabled)<span class="badge">Desabilitada</span>@endunless</td>
                        <td><span class="check-history__status check-history__status--{{ $style }}">{{ $label }}</span>
                            @if($execution?->status === 'pending')<p class="muted">Na fila desde {{ $execution->created_at->format('d/m/Y H:i:s') }}</p>@endif
                        </td>
                        <td>
                            @if($lastStarted)
                                <time datetime="{{ $lastStarted->started_at->toIso8601String() }}">{{ $lastStarted->started_at->format('d/m/Y H:i:s') }}</time>
                                <p class="muted">{{ $lastStarted->started_at->diffForHumans() }}</p>
                                @if($lastStarted->finished_at)<p class="muted">Fim: {{ $lastStarted->finished_at->format('d/m/Y H:i:s') }}</p>@endif
                            @else
                                Ainda não rodou
                            @endif
                        </td>
                        <td class="check-report__conditions">
                            @if($execution?->error_message)<p class="check-report__error">{{ \Illuminate\Support\Str::limit($execution->error_message, 300) }}</p>@endif
                            @if(isset($observed['cache_status']))<p>Cache: <strong>{{ $observed['cache_status'] }}</strong></p>@endif
                            @if(isset($observed['console_error_count']))<p>Console: {{ $observed['console_error_count'] }} erro(s) · JavaScript: {{ $observed['page_error_count'] ?? 0 }} exceção(ões)</p>@endif
                            @foreach($failedConditions as $condition)
                                <div class="check-report__condition">
                                    <strong>{{ \App\Livewire\Stores\CheckDefinitions::FIELD_LABELS[$condition['field']] ?? $condition['field'] }}</strong>
                                    <p>Esperado: {{ \App\Livewire\Stores\CheckDefinitions::OPERATOR_LABELS[$condition['operator'] ?? ''] ?? ($condition['operator'] ?? '') }} {{ $formatValue($condition['expected'] ?? null) }}</p>
                                    <p>Observado: {{ $formatValue($condition['actual'] ?? null) }}</p>
                                </div>
                            @endforeach
                            @if($state === 'success')<p>{{ $evaluations->count() }} condição(ões) atendida(s).</p>
                            @elseif(in_array($state, ['pending', 'running'], true))<p class="muted">Aguardando o resultado desta execução.</p>
                            @elseif($state === 'never')<span class="muted">Nenhum resultado registrado.</span>
                            @elseif($failedConditions->isEmpty() && !$execution?->error_message && !$observed)<span class="muted">Sem detalhes salvos.</span>@endif
                        </td>
                        <td>
                            @if(!$assignment->enabled)Desabilitada
                            @elseif($assignment->next_run_at)<time datetime="{{ $assignment->next_run_at->toIso8601String() }}">{{ $assignment->next_run_at->format('d/m/Y H:i') }}</time>
                            @else<span class="muted">Não agendada</span>@endif
                            <p class="muted">Intervalo: {{ $assignment->interval_minutes }} min</p>
                        </td>
                        <td>@if($execution)<button type="button" class="btn btn-secondary" wire:click="openDetails({{ $execution->id }})" aria-haspopup="dialog" aria-label="Ver detalhes de {{ $assignment->checkDefinition->name }} em {{ $assignment->store->name }}">Ver detalhes</button>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Nenhuma loja encontrada para os filtros selecionados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $assignments->links() }}

    @if($selectedExecution)
        <x-checks.execution-history-details :execution="$selectedExecution" :store="$selectedExecution->checkAssignment->store" />
    @endif
</div>
