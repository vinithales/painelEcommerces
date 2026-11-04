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
                                @php $canExecute = in_array($definition->executor_type, ['http', 'browser'], true) && $definition->assignments->contains('enabled', true); @endphp
                                <button type="button" class="btn btn-secondary definition-run" wire:click="openExecution({{ $definition->id }})" wire:loading.attr="disabled" wire:target="openExecution" @disabled(!$canExecute) aria-label="Executar {{ $definition->name }}" aria-haspopup="dialog">
                                    <x-icon name="play" width="16" height="16"/> Executar
                                </button>
                                @can('manage')
                                    <button type="button" class="btn btn-secondary" wire:click="edit({{ $definition->id }})">Editar e associar</button>
                                @endcan
                            </div>
                            @if(!$canExecute)
                                <p class="definition-actions__hint">{{ ! in_array($definition->executor_type, ['http', 'browser'], true) ? 'Execução disponível para HTTP' : 'Nenhuma loja habilitada' }}</p>
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
        <aside class="drawer definition-wizard" role="dialog" aria-modal="true" aria-labelledby="definition-form-title"
            x-data="{ step: @entangle('formStep') }" x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeForm()"
            x-effect="step; $nextTick(() => { $refs.content.scrollTop = 0; $refs.stepHeading?.focus() })">
            <header class="definition-wizard__header">
                <div>
                    <h2 id="definition-form-title">{{ $editingId ? 'Editar definição' : 'Nova definição' }}</h2>
                    <p class="muted">Uma verificação, do seu jeito. Configure em quatro etapas.</p>
                </div>
                <button type="button" class="btn btn-secondary" wire:click="closeForm">Fechar</button>
            </header>

            <div class="definition-wizard__layout">
                <nav class="definition-wizard__navigation" aria-label="Etapas da definição">
                    <p class="definition-wizard__eyebrow">CONFIGURAR VERIFICAÇÃO</p>
                    <ol>
                        @foreach(\App\Livewire\Stores\CheckDefinitions::FORM_STEPS as $number => $step)
                            <li class="definition-wizard__step {{ $formStep === $number ? 'is-current' : ($formStep > $number ? 'is-complete' : '') }}" @if($formStep === $number) aria-current="step" @endif>
                                <span class="definition-wizard__step-number" aria-hidden="true">{{ $formStep > $number ? '✓' : str_pad($number, 2, '0', STR_PAD_LEFT) }}</span>
                                <span><strong>{{ $step['title'] }}</strong><small>{{ $step['description'] }}</small></span>
                            </li>
                        @endforeach
                    </ol>
                    <div class="definition-wizard__tip">
                        <strong>Uma regra, várias lojas.</strong>
                        <p>Você define o que verificar uma vez e escolhe onde e com que frequência executar.</p>
                    </div>
                </nav>
                <form wire:submit="{{ $formStep === 4 ? 'save' : 'nextStep' }}" class="definition-form definition-wizard__form">
                    <div class="definition-wizard__content" x-ref="content">
                        <div class="definition-wizard__heading">
                            <span class="definition-wizard__eyebrow">ETAPA {{ str_pad($formStep, 2, '0', STR_PAD_LEFT) }} DE 04</span>
                            <h3 tabindex="-1" x-ref="stepHeading">{{ \App\Livewire\Stores\CheckDefinitions::FORM_STEPS[$formStep]['title'] }}</h3>
                            <p>{{ [1 => 'Comece identificando o que você quer acompanhar.', 2 => 'Ajuste como a verificação acessa sua aplicação.', 3 => 'Defina o que uma resposta saudável precisa atender.', 4 => 'Escolha as lojas, ajuste a frequência e conclua.'][$formStep] }}</p>
                        </div>
            @if($errors->any())
                <div class="error" role="alert">
                    <strong>Revise os campos abaixo.</strong>
                    <ul>
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @if($formStep === 1)
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
                            <select wire:model.live="executorType">
                                @foreach($executorTypes as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                            </select>
                        </label>
                    </div>
                </section>
            @elseif($formStep === 2)
                <section aria-labelledby="request-settings-title">
                    <div class="panel__header">
                        <h3 id="request-settings-title">O que vamos verificar?</h3>
                        <p>Defina a página e como ela deve ser acessada.</p>
                    </div>
                    @if($executorType === 'http')
                        <div class="field-grid">
                            <label class="field--full">Caminho da página
                                <input wire:model="httpConfiguration.path" placeholder="/ ou /health" maxlength="2048" required>
                                <small class="muted">Comece com /. O endereço da loja será usado automaticamente.</small>
                                @error('configuration.path')<span class="field-error">{{ $message }}</span>@enderror
                            </label>
                            <label>Método de acesso
                                <select wire:model="httpConfiguration.method">
                                    <option value="GET">GET — Carregar a página</option>
                                    <option value="HEAD">HEAD — Consultar apenas os cabeçalhos</option>
                                    @foreach(['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method)
                                        <option value="{{ $method }}">{{ $method }}</option>
                                    @endforeach
                                </select>
                                @error('configuration.method')<span class="field-error">{{ $message }}</span>@enderror
                            </label>
                            <label>Requisições (a última será avaliada)
                                <input type="number" min="1" max="3" placeholder="1" wire:model="httpConfiguration.samples">
                                <small>Use 3 para aquecer o cache. Os cabeçalhos ficam no histórico.</small>
                            </label>
                            <label>Tempo limite (segundos)
                                <input type="number" min="1" max="30" step="1" wire:model="httpConfiguration.timeout" required>
                                @error('configuration.timeout')<span class="field-error">{{ $message }}</span>@enderror
                            </label>
                            <label class="request-redirects field--full">
                                <input type="checkbox" wire:model="httpConfiguration.follow_redirects">
                                <span>Seguir redirecionamentos <small class="muted">Continuar quando a página encaminhar para outro endereço.</small></span>
                            </label>
                            @error('configuration.follow_redirects')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                    @elseif($executorType === 'browser')
                        <p>Registra console.error e exceções JavaScript da página e de seus scripts durante a observação.</p>
                        <div class="field-grid">
                            <label class="field--full">Caminho da página
                                <input wire:model="browserConfiguration.path" placeholder="/" maxlength="2048" required>
                            </label>
                            <label>Tempo limite de carregamento (segundos)
                                <input type="number" min="1" max="30" wire:model="browserConfiguration.timeout" required>
                            </label>
                            <label>Observar após carregar (ms)
                                <input type="number" min="0" max="10000" wire:model="browserConfiguration.wait_ms" required>
                                <small>5000 ms = 5 segundos. Abre apenas o caminho informado, sem cliques.</small>
                            </label>
                        </div>
                    @else
                        <label>Configuração avançada (JSON)
                            <textarea wire:model="configurationJson" rows="9" spellcheck="false"></textarea>
                            @error('configurationJson')<span class="field-error">{{ $message }}</span>@enderror
                        </label>
                    @endif
                </section>
            @elseif($formStep === 3)
                <section aria-labelledby="assertion-builder-title">
                    <div class="panel__header">
                        <h3 id="assertion-builder-title">Quando considerar que está tudo certo?</h3>
                        <p>Escolha o campo, a comparação e o valor esperado.</p>
                    </div>
                    <p class="condition-builder__note">Todas as condições abaixo precisam ser atendidas.</p>
                    <div class="condition-builder">
                        @foreach($assertions as $index => $assertion)
                            @php
                                $field = $assertion['field'] ?? '';
                                $numeric = in_array($field, ['status_code', 'response_time', 'console_error_count', 'page_error_count'], true);
                                $operators = $numeric
                                    ? \App\Actions\Checks\CheckDefinitionValidator::NUMERIC_ASSERTION_OPERATORS
                                    : \App\Actions\Checks\CheckDefinitionValidator::TEXT_ASSERTION_OPERATORS;
                            @endphp
                            <fieldset class="condition-row" wire:key="condition-{{ $index }}">
                                <legend>Condição {{ $index + 1 }}</legend>
                                <div class="condition-row__fields">
                                    <label>Campo
                                        <select wire:model.live="assertions.{{ $index }}.field">
                                            @if(!array_key_exists($field, \App\Livewire\Stores\CheckDefinitions::FIELD_LABELS))
                                                <option value="{{ $field }}">{{ $field }}</option>
                                            @endif
                                            @foreach(\App\Livewire\Stores\CheckDefinitions::FIELD_LABELS as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error("assertions.$index.field")<span class="field-error">{{ $message }}</span>@enderror
                                    </label>
                                    <label>Operador
                                        <select wire:model="assertions.{{ $index }}.operator">
                                            @if(!in_array($assertion['operator'] ?? '', $operators, true))
                                                <option value="{{ $assertion['operator'] ?? '' }}" disabled>Selecione uma comparação disponível</option>
                                            @endif
                                            @foreach($operators as $operator)
                                                <option value="{{ $operator }}">{{ \App\Livewire\Stores\CheckDefinitions::OPERATOR_LABELS[$operator] }}</option>
                                            @endforeach
                                        </select>
                                        @error("assertions.$index.operator")<span class="field-error">{{ $message }}</span>@enderror
                                    </label>
                                    <label>Esperado{{ $field === 'response_time' ? ' (ms)' : '' }}
                                        <input wire:key="expected-{{ $index }}-{{ $field }}" type="{{ $numeric ? 'number' : 'text' }}"
                                            wire:model="assertions.{{ $index }}.expected"
                                            @if($numeric) step="1" min="{{ $field === 'status_code' ? 100 : 0 }}" @endif
                                            @if($field === 'status_code') max="599" @endif
                                            placeholder="{{ $field === 'status_code' ? 'Ex.: 200' : ($field === 'response_time' ? 'Ex.: 3000' : 'Digite o texto esperado') }}" required>
                                        @error("assertions.$index.expected")<span class="field-error">{{ $message }}</span>@enderror
                                    </label>
                                </div>
                                <div class="condition-row__footer">
                                    <small class="muted">{{ $field === 'response_time' ? '1.000 ms = 1 segundo.' : ($field === 'status_code' ? '200 indica que a página respondeu com sucesso.' : 'A comparação usa o texto completo e diferencia maiúsculas e minúsculas.') }}</small>
                                    <button type="button" class="condition-row__remove" wire:click="removeAssertion({{ $index }})" @disabled(count($assertions) === 1) aria-label="Remover condição {{ $index + 1 }}">Remover</button>
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                    @error('assertions')<p class="field-error">{{ $message }}</p>@enderror
                    <button type="button" class="btn btn-secondary condition-builder__add" wire:click="addAssertion">+ Adicionar condição</button>
                </section>
            @elseif($formStep === 4)
                <section>
                    <div class="definition-wizard__summary">
                        <div><small>Verificação</small><strong>{{ $name }}</strong></div>
                        <div><small>Condições</small><strong>{{ count($assertions) }} {{ count($assertions) === 1 ? 'condição' : 'condições' }}</strong></div>
                        <div><small>Lojas selecionadas</small><strong>{{ collect($storeAssignments)->where('selected', true)->count() }}</strong></div>
                    </div>
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
                            <div class="definition-wizard__empty"><strong>Nenhuma loja cadastrada</strong><p>Você pode concluir a definição agora e associar as lojas depois de cadastrá-las.</p></div>
                        @endforelse
                    </div>
                    @error('storeAssignments')<p class="field-error">{{ $message }}</p>@enderror
                    <p class="definition-wizard__save-note">Ao concluir, a definição e as associações selecionadas serão salvas.</p>
                </section>
            @endif
                    </div>
                <div class="definition-wizard__footer">
                    @if($formStep > 1)
                        <button type="button" class="btn btn-secondary" wire:click="previousStep" wire:loading.attr="disabled">← Voltar</button>
                    @else
                        <button type="button" class="btn btn-secondary" wire:click="closeForm">Cancelar</button>
                    @endif
                    <span class="definition-wizard__progress">{{ $formStep }} de 4 etapas</span>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="nextStep,save">{{ $formStep === 4 ? 'Concluir' : 'Próximo →' }}</span>
                        <span wire:loading wire:target="nextStep,save">{{ $formStep === 4 ? 'Concluindo…' : 'Validando…' }}</span>
                    </button>
                </div>
            </form>
            </div>
        </aside>
    @endif
</div>
