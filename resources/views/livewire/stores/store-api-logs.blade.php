<div class="stack-xl">
    <section class="hero-grid">
        <article class="hero-card hero-card--wide">
            <span class="eyebrow">{{ $store->name }}</span>
            <h1>Logs de API</h1>
            <p>Histórico completo das operações de consulta e atualização na API da plataforma.</p>
        </article>
    </section>

    <section class="panel stack-lg">
        <div class="toolbar">
            <label class="field field--filter">
                <span>Operação</span>
                <select wire:model.live="operationType">
                    <option value="all">Todas</option>
                    <option value="list">Consulta</option>
                    <option value="update">Atualização</option>
                </select>
            </label>
            <label class="field field--filter">
                <span>Recurso</span>
                <select wire:model.live="resourceType">
                    <option value="all">Todos</option>
                    <option value="product">Produto</option>
                    <option value="order">Pedido</option>
                </select>
            </label>
            <label class="field field--filter">
                <span>Status</span>
                <select wire:model.live="status">
                    <option value="all">Todos</option>
                    <option value="ok">OK</option>
                    <option value="failed">Failed</option>
                </select>
            </label>
            <label class="field">
                <span>De</span>
                <input type="date" wire:model.live="dateFrom" />
            </label>
            <label class="field">
                <span>Até</span>
                <input type="date" wire:model.live="dateTo" />
            </label>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Quando</th>
                        <th>Operação</th>
                        <th>Recurso</th>
                        <th>ID do recurso</th>
                        <th>Status</th>
                        <th>Error</th>
                        <th class="table-actions">Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr wire:key="log-{{ $log->id }}">
                            <td class="table-subtitle">{{ $log->created_at->diffForHumans() }}</td>
                            <td>{{ $log->operation_type }}</td>
                            <td>{{ $log->resource_type }}</td>
                            <td class="table-subtitle">{{ $log->resource_id ?? '—' }}</td>
                            <td>
                                <span class="check-badge check-badge--{{ $log->status === 'ok' ? 'ok' : 'error' }}">
                                    {{ $log->status }}
                                </span>
                            </td>
                            <td class="table-subtitle">{{ substr($log->error_message ?? '—', 0, 60) }}</td>
                            <td class="table-actions">
                                <button
                                    type="button"
                                    class="button button--small button--ghost"
                                    wire:click="toggleExpand({{ $log->id }})"
                                >
                                    {{ in_array($log->id, $expandedIds) ? 'Ocultar' : 'Exibir' }}
                                </button>
                            </td>
                        </tr>
                        @if (in_array($log->id, $expandedIds))
                            <tr wire:key="log-detail-{{ $log->id }}">
                                <td colspan="7" style="padding: 0.75rem 1rem; background: var(--bg-surface);">
                                    <div class="stack-lg">
                                        @if ($log->payload)
                                            <div>
                                                <strong>Dados enviados:</strong>
                                                <pre style="overflow: auto; max-height: 200px; font-size: 0.8rem;">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        @endif
                                        @if ($log->response)
                                            <div>
                                                <strong>Resposta da API:</strong>
                                                <pre style="overflow: auto; max-height: 200px; font-size: 0.8rem;">{{ json_encode($log->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        @endif
                                        @if ($log->error_message)
                                            <div>
                                                <strong>Erro:</strong>
                                                <p class="table-subtitle">{{ $log->error_message }}</p>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="table-subtitle" style="text-align: center;">Nenhum registro de log encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $logs->links() }}
    </section>
</div>
