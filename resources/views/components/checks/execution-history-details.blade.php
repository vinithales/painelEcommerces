@props(['execution', 'store'])

@php
    $snapshot = $execution->definition_snapshot ?? [];
    $result = $execution->result ?? [];
    $observed = $result['observed'] ?? [];
    $assertions = $result['assertions'] ?? [];
    $statusLabel = ['pending' => 'Na fila', 'running' => 'Executando', 'completed' => 'Concluída', 'failed' => 'Falhou', 'error' => 'Erro'][$execution->status] ?? $execution->status;
    $outcomeLabel = ['success' => 'Sucesso', 'passed' => 'Sucesso', 'failed' => 'Falhou'][$execution->outcome] ?? $execution->outcome;
    $outcomeStyle = in_array($execution->outcome, ['success', 'passed'], true) ? 'success' : ($execution->outcome === 'failed' ? 'fail' : 'pending');
    $formatValue = static fn ($value) => $value === null ? '—' : (is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
@endphp

<dialog
    class="modal check-history__dialog"
    wire:ignore.self
    wire:key="check-history-details-{{ $execution->id }}"
    x-data="{ destroy() { if (this.$el.open) this.$el.close() } }"
    x-init="$el.showModal(); $nextTick(() => $el.querySelector('[data-close-details]')?.focus())"
    x-on:cancel.prevent="$wire.closeDetails()"
    aria-labelledby="check-history-details-title"
>
    <header class="check-history__dialog-header">
        <div>
            <span class="check-history__eyebrow">Execução #{{ $execution->id }} · {{ $store->name }}</span>
            <h2 id="check-history-details-title">{{ ($snapshot['name'] ?? null) ?: 'Verificação sem nome' }}</h2>
        </div>
        <button type="button" class="btn btn-secondary" data-close-details wire:click="closeDetails">Fechar</button>
    </header>

    <div class="check-history__dialog-content">
        <div class="check-history__detail-status">
            <span class="check-history__status check-history__status--{{ in_array($execution->status, ['error', 'failed'], true) ? 'fail' : ($execution->status === 'running' ? 'running' : 'completed') }}">{{ $statusLabel }}</span>
            @if ($outcomeLabel)
                <span class="check-history__status check-history__status--{{ $outcomeStyle }}">{{ $outcomeLabel }}</span>
            @endif
        </div>

        <dl class="check-history__facts">
            <div><dt>Início</dt><dd>{{ $execution->started_at?->format('d/m/Y H:i:s') ?? 'Aguardando' }}</dd></div>
            <div><dt>Fim</dt><dd>{{ $execution->finished_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
            <div><dt>Nível</dt><dd>{{ $snapshot['level'] ?? '—' }}</dd></div>
            <div><dt>Executor</dt><dd>{{ strtoupper($snapshot['executor_type'] ?? '—') }}</dd></div>
        </dl>

        @if ($execution->error_message)
            <div class="check-history__detail-error" role="alert">
                <strong>Erro na execução</strong>
                <p>{{ $execution->error_message }}</p>
            </div>
        @endif

        <section class="check-history__detail-section" aria-labelledby="check-history-observed-title">
            <h3 id="check-history-observed-title">Resposta observada</h3>
            @if ($observed)
                <dl class="check-history__facts">
                    <div><dt>Código HTTP</dt><dd>{{ $observed['status_code'] ?? '—' }}</dd></div>
                    <div><dt>Tempo de resposta</dt><dd>{{ isset($observed['response_time']) ? $observed['response_time'].' ms' : '—' }}</dd></div>
                    <div class="check-history__fact-wide"><dt>URL</dt><dd>{{ $observed['url'] ?? '—' }}</dd></div>
                </dl>
                @if(isset($observed['cache_status']))
                    <p>Cache: <strong>{{ $observed['cache_status'] }}</strong> · Cabeçalho: {{ $observed['cache_header'] ?? 'Nenhum cabeçalho de cache reconhecido' }}</p>
                @endif
                @if (array_key_exists('body', $observed) && $observed['body'] !== null && $observed['body'] !== '')
                    <details class="check-history__body-details">
                        <summary>Ver HTML da resposta</summary>
                        <pre>{{ $observed['body'] }}</pre>
                    </details>
                @endif
            @else
                <p class="check-history__muted">Ainda não há uma resposta registrada para esta execução.</p>
            @endif
        </section>

        @foreach(['headers' => 'Cabecalhos da ultima resposta', 'samples' => 'Acessos para verificar cache', 'console_errors' => 'Erros de console', 'page_errors' => 'Excecoes JavaScript', 'blocked_requests' => 'Requisicoes bloqueadas pelo monitor'] as $key => $label)
            @if(array_key_exists($key, $observed))
                <section class="check-history__detail-section">
                    <h3>{{ $label }}</h3>
                    @if($observed[$key])
                        <pre style="white-space: pre-wrap; overflow-wrap: anywhere">{{ json_encode($observed[$key], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    @else
                        <p>Nenhum registro.</p>
                    @endif
                </section>
            @endif
        @endforeach
        @if($observed['errors_truncated'] ?? false)<p>Exibindo os primeiros 100 erros de cada tipo. A contagem inclui todos.</p>@endif

        <section class="check-history__detail-section" aria-labelledby="check-history-assertions-title">
            <h3 id="check-history-assertions-title">Condições avaliadas</h3>
            @forelse ($assertions as $assertion)
                <div class="check-history__assertion">
                    <div class="check-history__assertion-heading">
                        <strong>{{ $assertion['field'] ?? 'Campo sem nome' }}</strong>
                        <span class="check-history__status check-history__status--{{ ($assertion['passed'] ?? false) ? 'success' : 'fail' }}">{{ ($assertion['passed'] ?? false) ? 'Aprovada' : 'Falhou' }}</span>
                    </div>
                    <dl class="check-history__assertion-values">
                        <div><dt>Operador</dt><dd>{{ $assertion['operator'] ?? '-' }}</dd></div>
                        <div><dt>Esperado</dt><dd>{{ $formatValue($assertion['expected'] ?? null) }}</dd></div>
                        <div><dt>Obtido</dt><dd>{{ $formatValue($assertion['actual'] ?? null) }}</dd></div>
                    </dl>
                </div>
            @empty
                <p class="check-history__muted">Nenhuma condição avaliada nesta execução.</p>
            @endforelse
        </section>
    </div>
</dialog>
