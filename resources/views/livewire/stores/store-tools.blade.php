<div wire:poll.3s>
    <div class="page-header">
        <div>
            <h2>Ferramentas de API</h2>
            <p>{{ $store->name }} · Consultas predefinidas de leitura</p>
        </div>
    </div>
    <div class="panel">
        <form wire:submit="execute">
            <div class="field-grid"><label>Operação<select
                        wire:model.live="operation">@foreach(['api_auth' => 'Testar autenticação', 'product_id' => 'Produto por ID', 'product_sku' => 'Produto por SKU', 'order_id' => 'Pedido por ID', 'store_info' => 'Informações da loja'] as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select></label>
                @if(in_array($operation, ['product_id', 'product_sku', 'order_id']))<label>ID / SKU<input
                wire:model="identifier"></label>@endif
            </div>
            @foreach($errors->all() as $error)
            <p class="error">{{ $error }}</p>@endforeach<button class="btn btn-primary"
                wire:loading.attr="disabled">Executar consulta</button>
        </form>
    </div>
    @if($task)
        <div class="panel">@if(!$task['complete'])
        <p role="status">Consulta na fila. Aguardando worker…</p>@elseif(isset($task['error']))
            <p class="error">{{ $task['error'] }}</p>@else
                <h3>Resultado</h3>
                <p>HTTP {{ $task['result']['http_status'] ?? '—' }} · {{ $task['result']['duration_ms'] ?? $task['duration_ms'] }}
                    ms</p>
            <pre>{{ json_encode($task['result'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>@endif
    </div>@endif
</div>