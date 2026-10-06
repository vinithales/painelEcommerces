@props(['definition', 'scope', 'assignmentId', 'assignmentIds' => [], 'progress' => [], 'started' => false])
@php
    $enabledAssignments = $definition->assignments->where('enabled', true)->sortBy(fn ($assignment) => $assignment->store->name);
    $disabledCount = $definition->assignments->count() - $enabledAssignments->count();
    $targets = match ($scope) {
        'single' => $enabledAssignments->where('id', $assignmentId),
        'group' => $enabledAssignments->whereIn('id', $assignmentIds),
        default => $enabledAssignments,
    };
    $canExecute = $definition->executor_type === 'http' && $targets->isNotEmpty();
    $finished = collect($progress)->whereIn('status', ['success', 'failed', 'error'])->count();
    $failures = collect($progress)->whereIn('status', ['failed', 'error'])->count();
    $active = $started && $finished < count($progress);
    $statusLabels = ['pending' => 'Na fila', 'running' => 'Executando', 'success' => 'Concluída', 'failed' => 'Falhou', 'error' => 'Erro'];
@endphp

<dialog class="modal check-execution" wire:ignore.self x-data="{ destroy() { if (this.$el.open) this.$el.close() } }" x-init="$el.showModal()" x-on:cancel.prevent="$wire.closeExecution()" aria-labelledby="execution-title" aria-describedby="execution-description" wire:key="execution-{{ $definition->id }}">
    <header class="check-execution__header">
        <div>
            <span class="check-execution__eyebrow">Execução imediata</span>
            <h2 id="execution-title" tabindex="-1" autofocus>{{ $definition->name }}</h2>
            <p id="execution-description" class="muted">{{ $started ? 'Acompanhe o resultado de cada loja.' : 'Escolha onde executar esta verificação.' }}</p>
        </div>
        <button type="button" class="btn btn-secondary" wire:click="closeExecution" wire:loading.attr="disabled" wire:target="executeSelected">Fechar</button>
    </header>

    @if(!$started)
        <div>
            <fieldset class="check-execution__options" wire:loading.attr="disabled" wire:target="executeSelected">
                <legend>Onde executar?</legend>
                @foreach(['all' => ['Todas as lojas habilitadas', $enabledAssignments->count().($enabledAssignments->count() === 1 ? ' loja disponível' : ' lojas disponíveis')], 'single' => ['Uma loja', 'Escolha uma loja específica'], 'group' => ['Selecionar um grupo', 'Marque as lojas que deseja executar']] as $value => [$label, $description])
                    <label class="execution-option execution-option--compact {{ $scope === $value ? 'is-selected' : '' }}">
                        <input type="radio" wire:model.live="executionScope" value="{{ $value }}" @checked($scope === $value)>
                        <span><strong>{{ $label }}</strong><small>{{ $description }}</small></span>
                    </label>
                @endforeach
            </fieldset>

            @if($scope === 'single')
                <label class="check-execution__store" for="execution-store">Loja
                    <select id="execution-store" wire:model.live="executionAssignmentId" wire:loading.attr="disabled" wire:target="executeSelected" @if($errors->has('executionAssignmentId')) aria-invalid="true" aria-describedby="execution-store-error" @endif>
                        <option value="">Selecione uma loja</option>
                        @foreach($enabledAssignments as $assignment)
                            <option value="{{ $assignment->id }}" @selected($assignment->id === $assignmentId)>{{ $assignment->store->name }}</option>
                        @endforeach
                    </select>
                </label>
            @elseif($scope === 'group')
                <fieldset class="execution-store-list" wire:loading.attr="disabled" wire:target="executeSelected">
                    <legend>Lojas <span class="muted">· {{ $targets->count() }} selecionadas</span></legend>
                    <div class="execution-store-list__items">
                        @forelse($enabledAssignments as $assignment)
                            <label class="execution-store-list__item" wire:key="execution-store-{{ $assignment->id }}">
                                <input type="checkbox" wire:model.live="executionAssignmentIds" value="{{ $assignment->id }}">
                                <span>{{ $assignment->store->name }}</span>
                                <small>{{ $assignment->interval_minutes }} min</small>
                            </label>
                        @empty
                            <p class="muted">Nenhuma loja habilitada.</p>
                        @endforelse
                    </div>
                </fieldset>
            @endif

            @error('executionAssignmentId')<p id="execution-store-error" class="field-error" role="alert">{{ $message }}</p>@enderror
            @error('executionAssignmentIds')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            @error('executionAssignmentIds.*')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            @error('executionScope')<p class="field-error" role="alert">{{ $message }}</p>@enderror

            <div class="check-execution__summary" aria-live="polite" aria-atomic="true">
                <strong>{{ $targets->count() }} {{ $targets->count() === 1 ? 'loja selecionada' : 'lojas selecionadas' }}</strong>
                <p class="muted">A execução só começa ao clicar em Executar.</p>
                @if($disabledCount)
                    <p class="muted">{{ $disabledCount }} {{ $disabledCount === 1 ? 'loja desabilitada ficará' : 'lojas desabilitadas ficarão' }} fora desta execução.</p>
                @endif
            </div>

            <footer class="panel__footer check-execution__footer">
                <button type="button" class="btn btn-secondary" wire:click="closeExecution" wire:loading.attr="disabled" wire:target="executeSelected">Cancelar</button>
                <button type="button" class="btn btn-primary" wire:click="executeSelected" wire:loading.attr="disabled" wire:target="executeSelected,executionScope,executionAssignmentId,executionAssignmentIds" @disabled(!$canExecute)>
                    <x-icon name="play" width="16" height="16"/>
                    <span wire:loading.remove wire:target="executeSelected">{{ $scope === 'single' ? 'Executar nesta loja' : 'Executar em '.$targets->count().($targets->count() === 1 ? ' loja' : ' lojas') }}</span>
                    <span wire:loading wire:target="executeSelected">Iniciando...</span>
                </button>
            </footer>
        </div>
    @else
        <section class="execution-monitor" @if($active) wire:poll.2s.keep-alive="refreshExecutionProgress" @endif aria-labelledby="execution-progress-title">
            <div class="execution-monitor__heading" role="status" aria-live="polite">
                <strong id="execution-progress-title">{{ $active ? 'Execução em andamento' : ($failures ? 'Execução finalizada com falhas' : 'Execução concluída') }}</strong>
                <span class="muted">{{ $finished }} de {{ count($progress) }} finalizadas</span>
            </div>
            <progress class="execution-monitor__bar" value="{{ $finished }}" max="{{ max(1, count($progress)) }}" aria-label="Lojas com execução finalizada"></progress>
            <ul class="execution-monitor__list">
                @foreach($progress as $id => $row)
                    <li class="execution-monitor__item" wire:key="execution-progress-{{ $id }}">
                        <div class="execution-monitor__row">
                            <strong>{{ $row['store'] }}</strong>
                            <span class="execution-status execution-status--{{ $row['status'] }}">
                                @if($row['status'] === 'running')<span class="cache-progress__spinner" aria-hidden="true"></span>@endif
                                {{ $statusLabels[$row['status']] ?? $row['status'] }}
                            </span>
                        </div>
                        @if($row['message'])<p class="execution-monitor__error" role="alert">{{ $row['message'] }}</p>@endif
                    </li>
                @endforeach
            </ul>
            @if($failures)<p class="execution-monitor__error" role="alert">{{ $failures }} {{ $failures === 1 ? 'loja apresentou erro ou falha' : 'lojas apresentaram erro ou falha' }}. Confira os detalhes acima.</p>@endif
            <p class="muted execution-monitor__note">Você pode fechar este painel. As execuções enviadas continuam em andamento.</p>
        </section>
        <footer class="panel__footer check-execution__footer">
            <button type="button" class="btn btn-secondary" wire:click="closeExecution">{{ $active ? 'Fechar acompanhamento' : 'Fechar' }}</button>
        </footer>
    @endif
</dialog>
