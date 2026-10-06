<div class="stack-xl">
    <div class="page-header">
        <div>
            <h2>Histórico de verificações</h2>
            <p>{{ $store->name }}</p>
        </div>
    </div>

    <div class="panel stack-lg">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Verificação</th>
                        <th>Início</th>
                        <th>Estado</th>
                        <th>Conclusão</th>
                        <th>Mensagem de erro</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($executions as $execution)
                        <tr wire:key="check-execution-{{ $execution->id }}">
                            <td>{{ ($execution->definition_snapshot['name'] ?? null) ?: 'Sem nome' }}</td>
                            <td>{{ $execution->started_at?->format('d/m/Y H:i:s') ?? 'Aguardando' }}</td>
                            <td>{{ $execution->status }}</td>
                            <td>{{ $execution->outcome ?? '—' }}</td>
                            <td>{{ $execution->error_message ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty">Nenhuma execução de verificação encontrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $executions->links() }}
    </div>
</div>
