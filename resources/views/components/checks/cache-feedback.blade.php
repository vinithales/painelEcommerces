@props(['observed'])
@php($feedback = app(\App\Services\Checks\CheckExecutionFeedback::class)->cache($observed))
<div class="check-feedback">
    <strong>{{ $feedback['title'] }}</strong>
    <p>{{ $feedback['explanation'] }}</p>
    @if($observed['cache_header'] ?? null)
        <p class="muted">Cabeçalho: {{ $observed['cache_header'] }}
            @if(isset($observed['headers'][$observed['cache_header']]))
                · Valor: {{ \Illuminate\Support\Str::limit($observed['headers'][$observed['cache_header']], 180) }}
            @endif
        </p>
    @endif
    @if($feedback['action'])<p class="check-feedback__action"><strong>Como investigar:</strong> {{ $feedback['action'] }}</p>@endif
</div>
