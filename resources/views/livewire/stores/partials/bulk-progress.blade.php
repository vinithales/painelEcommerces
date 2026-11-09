@if($bulkProgress)
<section class="panel"
    wire:key="bulk-progress-{{ $bulkBatchId }}-{{ $bulkProgress['active'] ? 'active' : 'finished' }}"
    x-data="{ timer: null, destroy() { clearTimeout(this.timer) } }"
    @if($bulkProgress['active'])
        wire:poll.2s="pollBulkProgress"
    @else
        x-init="timer = setTimeout(() => $wire.dismissBulkProgress(@js($bulkBatchId)), 10000)"
    @endif
    aria-labelledby="bulk-progress-title">
    <div class="cache-progress__header" role="status" aria-live="polite" aria-atomic="true">
        <strong id="bulk-progress-title">
            @if($bulkProgress['active'])
                <span class="cache-progress__spinner" aria-hidden="true"></span> Edição em massa em andamento
            @elseif($bulkProgress['cancelled'])
                Edição em massa interrompida
            @elseif($bulkProgress['failed'])
                Edição em massa concluída com falhas
            @else
                Edição em massa concluída
            @endif
        </strong>
        <span>{{ $bulkProgress['processed'] }} de {{ $bulkProgress['total'] }} registros · {{ $bulkProgress['percent'] }}%</span>
    </div>
    <div class="cache-progress__bar" role="progressbar" aria-label="Progresso da edição em massa" aria-valuemin="0" aria-valuemax="{{ $bulkProgress['total'] }}" aria-valuenow="{{ $bulkProgress['processed'] }}">
        <div class="cache-progress__fill" style="width: {{ $bulkProgress['percent'] }}%"></div>
    </div>
    <p class="muted">{{ $bulkProgress['succeeded'] }} com sucesso · {{ $bulkProgress['failed'] }} com falha · {{ $bulkProgress['total'] - $bulkProgress['processed'] }} pendentes</p>
    @if($bulkProgress['active'])<p class="muted">Aguardando ou processando os registros na fila. O progresso é atualizado automaticamente.</p>@endif
    @if($bulkProgress['failed'])<p><a wire:navigate href="{{ route('stores.api-logs', $store) }}">Ver detalhes das falhas no histórico de API</a></p>@endif
</section>
@endif
