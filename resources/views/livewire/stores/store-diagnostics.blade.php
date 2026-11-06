<div wire:poll.3s="pollTask">
<div class="page-header"><div><h2>Diagnóstico do servidor</h2><p>{{ $store->name }} · Operações autorizadas de leitura</p></div></div>
@foreach($errors->all() as $error)<p class="error">{{ $error }}</p>@endforeach
@can('configure')<details class="panel"><summary>Configurar conexão do agente</summary><form wire:submit="saveConnection"><label>Endpoint HTTPS<input wire:model="endpoint" placeholder="https://loja.exemplo/bring-agent"></label><label>Token (vazio preserva o atual)<input type="password" wire:model="token" autocomplete="new-password"></label><button class="btn btn-primary">Salvar conexão</button></form></details>@endcan
@can('configure')
<details class="panel"><summary>Configurar conexão SSH</summary>
    <form wire:submit="saveSsh">
        <div class="form-grid">
            <label>Servidor<input wire:model="ssh.host" placeholder="ssh.loja.com.br" required></label>
            <label>Porta<input type="number" wire:model="ssh.port" min="1" max="65535" required></label>
            <label>Usuário remoto<input wire:model="ssh.user" required></label>
            <label>Chave pública do servidor<input wire:model="ssh.host_key" placeholder="ssh-ed25519 AAAA…" required>
                <small>Copie o tipo e o valor da chave pública do host, confirmados com o administrador.</small></label>
            <label>Chave privada do usuário<textarea rows="4" wire:model="ssh.private_key" spellcheck="false" autocomplete="off"></textarea>
                <small>Com senha cadastrada para este IP e usuário, a conexão usa a senha. Para autenticar por chave, use uma chave sem senha.</small></label>
        </div>
        <button class="btn btn-primary">Salvar conexão SSH</button>
    </form>
</details>
@endcan
@if(!$store->serverConnection)<div class="panel"><h3>Agente não configurado</h3><p>Um administrador deve implantar o agente e cadastrar a conexão. As consultas de API continuam disponíveis.</p></div>@else
<div class="toolbar"><button class="btn btn-primary" wire:click="execute('server_status')">Estado do servidor</button>@if($store->isMagento2())<button class="btn btn-secondary" wire:click="execute('server_cron')">Diagnosticar cron</button>@endif</div>
@if($store->isMagento2())<div class="panel"><h3>Logs Magento</h3><div class="toolbar">
<label>Arquivo<select wire:model="logFile">@foreach(\App\Services\Infrastructure\AgentServerConnection::LOGS as $file)<option>{{ $file }}</option>@endforeach</select></label>
<button class="btn btn-secondary" wire:click="execute('server_logs')">Carregar / atualizar</button>
<label>Buscar<input wire:model.live.debounce.300ms="search"></label>
<label>Nível<select wire:model.live="logLevel"><option value="">Todos</option>@foreach(['DEBUG','INFO','WARNING','ERROR','CRITICAL','ALERT','EMERGENCY'] as $level)<option>{{ $level }}</option>@endforeach</select></label></div>
@if($logText!=='')<pre id="log-content">{{ $visibleLog }}</pre><div class="toolbar">
<button class="btn btn-secondary" x-on:click="navigator.clipboard.writeText(document.getElementById('log-content').textContent)">Copiar trecho</button><button class="btn btn-secondary" wire:click="download">Baixar trecho</button>
@if($cursor>0 && strlen($logText)<config('operations.log_max_bytes'))<button class="btn btn-secondary" wire:click="execute('server_logs',true)">Carregar mais</button>@endif</div><p class="muted">Trecho limitado a {{ config('operations.log_max_bytes')/1024 }} KiB. Stack traces preservados.</p>@endif</div>@endif
@if($task)<div class="panel">@if(!$task['complete'])<p role="status">Diagnóstico aguardando worker…</p>@elseif(isset($task['error']))<p class="error">{{ $task['error'] }}</p>@elseif($operation!=='server_logs')<pre>{{ json_encode($task['result'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>@endif</div>@endif
@endif</div>
