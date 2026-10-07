<div>
    <div class="page-header">
        <div>
            <h2>Verificação</h2>
            <p>Crie uma regra reutilizável e associe-a às lojas que devem executá-la.</p>
        </div>
        @can('manage')
            <button type="button" class="btn btn-primary" wire:click="create">Nova definição</button>
        @endcan
    </div>

    <div class="toolbar">
        <label>Buscar
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Nome da definição">
        </label>
        <label>Loja
            <select wire:model.live="storeId">
                <option value="">Todas as lojas</option>
                @foreach($stores as $store)<option value="{{ $store->id }}">{{ $store->name }}</option>@endforeach
            </select>
        </label>
    </div>

    @if($executionStatus)
        <div class="flash" role="status">{{ $executionStatus }}</div>
    @endif

    <div class="card table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Definição</th>
                    <th>Nível</th>
                    <th>Executor</th>
                    <th>Lojas associadas</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($definitions as $definition)
                    <tr wire:key="definition-{{ $definition->id }}">
                        <td>
                            <strong>{{ $definition->name }}</strong>
                            <p class="muted">Criada em {{ $definition->created_at->format('d/m/Y H:i') }}</p>
                        </td>
                        <td><span class="badge">{{ $definition->level }}</span></td>
                        <td>{{ $definition->executor_type }}</td>
                        <td>
                            <div x-data="{}">
                            <div class="definition-stores">
                                @forelse($definition->assignments->take(3) as $assignment)
                                    <span class="assignment-chip {{ $assignment->enabled ? '' : 'is-disabled' }}">
                                        {{ $assignment->store->name }} · {{ $assignment->interval_minutes }} min
                                        <span class="assignment-chip__state" title="Horário a partir do qual a execução poderá ser enfileirada. O início depende do Scheduler e da disponibilidade do worker.">
                                            Próxima execução:
                                            @if(!$assignment->enabled)
                                                Desabilitada
                                            @elseif(!$assignment->next_run_at)
                                                Não agendada
                                            @else
                                                <time datetime="{{ $assignment->next_run_at->toIso8601String() }}">{{ $assignment->next_run_at->format('d/m/Y H:i') }}</time>
                                            @endif
                                        </span>
                                    </span>
                                @empty
                                    <span class="muted">Nenhuma loja</span>
                                @endforelse
                            </div>
                            @if($definition->assignments->count() > 3)
                                <button type="button" class="btn btn-secondary definition-stores__all" x-on:click="$refs.associations.showModal()" aria-haspopup="dialog" aria-label="Ver todas as lojas de {{ $definition->name }}">
                                    Ver todas ({{ $definition->assignments->count() }})
                                </button>
                                <x-checks.assignment-list-dialog :definition="$definition" />
                            @endif
                            </div>
                        </td>
                        <td>
                            <div class="definition-actions">
                                @php $canExecute = $definition->executor_type === 'http' && $definition->assignments->contains('enabled', true); @endphp
                                <button type="button" class="btn btn-secondary definition-run" wire:click="openExecution({{ $definition->id }})" wire:loading.attr="disabled" wire:target="openExecution" @disabled(!$canExecute) aria-label="Executar {{ $definition->name }}" aria-haspopup="dialog">
                                    <x-icon name="play" width="16" height="16"/> Executar
                                </button>
                                @can('manage')
                                    <button type="button" class="btn btn-secondary" wire:click="edit({{ $definition->id }})">Editar e associar</button>
                                @endcan
                            </div>
                            @if(!$canExecute)
                                <p class="definition-actions__hint">{{ $definition->executor_type !== 'http' ? 'Execução disponível para HTTP' : 'Nenhuma loja habilitada' }}</p>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Nenhuma definição cadastrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $definitions->links() }}

    @if($executionDefinition)
        <x-checks.execution-dialog :definition="$executionDefinition" :scope="$executionScope" :assignment-id="$executionAssignmentId" :assignment-ids="$executionAssignmentIds" :progress="$executionProgress" :started="$executionStarted"/>
    @endif

    @if($formOpen)
        <div class="drawer-backdrop" wire:click="closeForm"></div>
        <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="definition-form-title">
            <header>
                <div>
                    <h2 id="definition-form-title">{{ $editingId ? 'Editar definição' : 'Nova definição' }}</h2>
                    <p class="muted">Configure a regra e escolha em quais lojas ela ficará disponível.</p>
                </div>
                <button type="button" class="btn btn-secondary" wire:click="closeForm">Fechar</button>
            </header>

            @if($errors->any())
                <div class="error" role="alert">
                    <strong>Revise os campos abaixo.</strong>
                    <ul>
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form wire:submit="save" class="definition-form">
                <section>
                    <div class="panel__header">
                        <h3>Definição</h3>
                        <p>Dados compartilhados por todas as lojas associadas.</p>
                    </div>
                    <div class="field-grid">
                        <label class="field--full">Nome
                            <input wire:model="name" maxlength="255" placeholder="Ex.: Disponibilidade da página inicial">
                            @error('name')<span class="field-error">{{ $message }}</span>@enderror
                        </label>
                        <label>Nível
                            <select wire:model="level">
                                @foreach($levels as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                            </select>
                        </label>
                        <label>Tipo de executor
                            <select wire:model="executorType">
                                @foreach($executorTypes as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                            </select>
                        </label>
                    </div>
                    <div class="json-fields">
                        <label>Configuração (JSON)
                            <textarea wire:model="configurationJson" rows="9" spellcheck="false"></textarea>
                            @error('configurationJson')<span class="field-error">{{ $message }}</span>@enderror
                        </label>
                        <label>Asserções (JSON)
                            <textarea wire:model="assertionsJson" rows="9" spellcheck="false"></textarea>
                            @error('assertionsJson')<span class="field-error">{{ $message }}</span>@enderror
                        </label>
                    </div>
                </section>

                <section>
                    <div class="panel__header">
                        <h3>Associação às lojas</h3>
                        <p>O intervalo e o estado são específicos de cada loja.</p>
                    </div>
                    <div class="assignment-grid">
                        @forelse($stores as $store)
                            @php $settings = $storeAssignments[(string) $store->id] ?? ['selected' => false, 'interval_minutes' => 5, 'enabled' => true]; @endphp
                            <article class="assignment-card {{ $settings['selected'] ? 'is-selected' : '' }}" wire:key="store-assignment-{{ $store->id }}">
                                <label class="assignment-card__title">
                                    <input type="checkbox" wire:model.live="storeAssignments.{{ $store->id }}.selected">
                                    <span>{{ $store->name }}</span>
                                </label>
                                <div class="assignment-card__settings">
                                    <label>Intervalo (min)
                                        <input type="number" min="1" max="10080" wire:model="storeAssignments.{{ $store->id }}.interval_minutes" @disabled(!$settings['selected'])>
                                    </label>
                                    <label class="assignment-card__enabled">
                                        <input type="checkbox" wire:model="storeAssignments.{{ $store->id }}.enabled" @disabled(!$settings['selected'])>
                                        Associação habilitada
                                    </label>
                                </div>
                            </article>
                        @empty
                            <p class="muted">Cadastre uma loja antes de criar associações.</p>
                        @endforelse
                    </div>
                    @error('storeAssignments')<p class="field-error">{{ $message }}</p>@enderror
                </section>

                <div class="panel__footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeForm">Cancelar</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">Salvar definição</span>
                        <span wire:loading wire:target="save">Salvando...</span>
                    </button>
                </div>
            </form>
        </aside>
    @endif
</div>
