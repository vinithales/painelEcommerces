@props(['definition'])

<dialog
    x-ref="associations"
    class="modal check-history__dialog assignment-list-dialog"
    wire:key="assignment-list-{{ $definition->id }}"
    x-data="{ destroy() { if (this.$el.open) this.$el.close() } }"
    aria-labelledby="assignment-list-title-{{ $definition->id }}"
    aria-describedby="assignment-list-hint-{{ $definition->id }}"
>
    <header class="check-history__dialog-header">
        <div>
            <h2 id="assignment-list-title-{{ $definition->id }}">Lojas associadas</h2>
            <p class="muted">{{ $definition->name }} · {{ $definition->assignments->count() }} lojas</p>
        </div>
        <button type="button" class="btn btn-secondary" autofocus x-on:click="$refs.associations.close()">Fechar</button>
    </header>
    <div class="check-history__dialog-content">
        <p class="muted" id="assignment-list-hint-{{ $definition->id }}">A próxima execução indica quando poderá ser enfileirada. O início depende do Scheduler e da disponibilidade do worker.</p>
        <div class="table-wrap">
            <table class="data-table assignment-list-dialog__table">
                <thead>
                    <tr><th scope="col">Loja</th><th scope="col">Intervalo</th><th scope="col">Próxima execução</th></tr>
                </thead>
                <tbody>
                    @foreach($definition->assignments as $assignment)
                        <tr>
                            <th scope="row">{{ $assignment->store->name }}</th>
                            <td>{{ $assignment->interval_minutes }} min</td>
                            <td>
                                @if(!$assignment->enabled)
                                    <span class="muted">Desabilitada</span>
                                @elseif(!$assignment->next_run_at)
                                    <span class="muted">Não agendada</span>
                                @else
                                    <time datetime="{{ $assignment->next_run_at->toIso8601String() }}">{{ $assignment->next_run_at->format('d/m/Y H:i') }}</time>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</dialog>
