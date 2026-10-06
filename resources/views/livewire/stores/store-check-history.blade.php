<div class="stack-xl">
    <div class="page-header">
        <div>
            <h2>Histórico de verificações</h2>
            <p>{{ $store->name }}</p>
        </div>
        <span class="check-history__count">{{ $executions->total() }} {{ $executions->total() === 1 ? 'execução' : 'execuções' }}</span>
    </div>

    <div class="panel stack-lg check-history">
        <div class="table-wrap">
            <table class="data-table check-history__table">
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
                        @php
                            $stateLabel = ['pending' => 'Na fila', 'running' => 'Executando', 'completed' => 'Concluída', 'failed' => 'Falhou', 'error' => 'Erro'][$execution->status] ?? $execution->status;
                            $stateStyle = ['pending' => 'pending', 'running' => 'running', 'completed' => 'completed', 'failed' => 'fail', 'error' => 'fail'][$execution->status] ?? 'pending';
                            $outcomeLabel = ['success' => 'Sucesso', 'passed' => 'Sucesso', 'failed' => 'Falhou'][$execution->outcome] ?? $execution->outcome;
                            $outcomeStyle = in_array($execution->outcome, ['success', 'passed'], true) ? 'success' : ($execution->outcome === 'failed' ? 'fail' : 'pending');
                            $rowStyle = in_array($execution->outcome, ['success', 'passed'], true) ? 'success' : (in_array($execution->status, ['failed', 'error'], true) || $execution->outcome === 'failed' ? 'fail' : 'neutral');
                        @endphp
                        <tr wire:key="check-execution-{{ $execution->id }}" class="check-history__row check-history__row--{{ $rowStyle }}">
                            <td><strong class="check-history__name">{{ ($execution->definition_snapshot['name'] ?? null) ?: 'Sem nome' }}</strong></td>
                            <td>
                                @if ($execution->started_at)
                                    <time class="check-history__time" datetime="{{ $execution->started_at->toIso8601String() }}">
                                        <span>{{ $execution->started_at->format('d/m/Y') }}</span>
                                        <small>{{ $execution->started_at->format('H:i:s') }}</small>
                                    </time>
                                @else
                                    <span class="check-history__muted">Aguardando</span>
                                @endif
                            </td>
                            <td><span class="check-history__status check-history__status--{{ $stateStyle }}">{{ $stateLabel }}</span></td>
                            <td>
                                @if ($outcomeLabel)
                                    <span class="check-history__status check-history__status--{{ $outcomeStyle }}">{{ $outcomeLabel }}</span>
                                @else
                                    <span class="check-history__muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($execution->error_message)
                                    <span class="check-history__error">{{ $execution->error_message }}</span>
                                @else
                                    <span class="check-history__muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="check-history__empty">Nenhuma execução de verificação encontrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $executions->links() }}
    </div>
</div>
