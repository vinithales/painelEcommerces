@props(['message', 'snapshot' => [], 'technical' => false])
@php($feedback = app(\App\Services\Checks\CheckExecutionFeedback::class)->error($message, $snapshot))
<div class="check-feedback check-feedback--error">
    <strong>{{ $feedback['title'] }}</strong>
    <p>{{ $feedback['explanation'] }}</p>
    <p><strong>{{ $feedback['impact'] }}</strong></p>
    <p class="check-feedback__action"><strong>Como investigar:</strong> {{ $feedback['action'] }}</p>
    @if($technical)
        <details class="check-feedback__technical">
            <summary>Detalhes técnicos</summary>
            <p>Mensagem original registrada pelo monitor:</p>
            <pre>{{ $message }}</pre>
        </details>
    @endif
</div>
