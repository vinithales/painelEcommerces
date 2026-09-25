<div wire:poll.5s>
<nav class="section-tabs" aria-label="Versões das verificações"><a wire:navigate href="{{ route('stores.checks') }}" class="section-tabs__item is-active" aria-current="page">Verificações</a><a wire:navigate href="{{ route('stores.check-definitions') }}" class="section-tabs__item">Verificações 2</a></nav>
<div class="page-header"><div><h2>Verificações</h2><p>Saúde operacional, histórico e alertas.</p></div>@can('manage')<button class="btn btn-primary" wire:click="create">Nova verificação</button>@endcan</div>
@foreach($errors->all() as $error)<p class="error">{{ $error }}</p>@endforeach
<div class="toolbar"><label>Buscar<input wire:model.live.debounce.300ms="search"></label><label>Loja<select wire:model.live="storeId"><option value="">Todas</option>@foreach($stores as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></label><label>Nível<select wire:model.live="levelFilter"><option value="">Todos</option>@foreach(['SERVER','APP','API','SITE'] as $v)<option>{{ $v }}</option>@endforeach</select></label></div>
<div class="card table-wrap"><table class="data-table"><thead><tr><th>Verificação</th><th>Loja / nível</th><th>Status</th><th>Intervalo</th><th>Última / próxima</th><th>Alertas</th><th></th></tr></thead><tbody>
@forelse($checks as $check)<tr><td><button class="btn btn-secondary" wire:click="open({{ $check->id }})">{{ $check->name }}</button><p class="muted">{{ $check->check_type }}</p></td><td>{{ $check->store->name }}<p>{{ $check->level }}</p></td><td><span class="badge badge-{{ $check->last_status }}">{{ $check->last_status }}</span><p>{{ $check->enabled?'Habilitada':'Desabilitada' }}</p></td><td>{{ $check->interval_seconds/60 }} min</td><td>{{ $check->last_run_at?->format('d/m H:i')??'Nunca' }}<p>{{ $check->next_run_at?->format('d/m H:i')??'—' }}</p></td><td>{{ $check->alert_status??'Sem alerta ativo' }}</td><td><button class="btn btn-secondary" wire:click="runNow({{ $check->id }})" wire:loading.attr="disabled">Executar</button></td></tr>
@empty<tr><td colspan="7" class="empty">Nenhuma verificação. Crie uma para acompanhar a operação.</td></tr>@endforelse
</tbody></table></div>{{ $checks->links() }}
@if($wizard)
<div class="drawer-backdrop"></div><aside class="drawer" role="dialog" aria-modal="true" aria-label="Configurar verificação">
<header><h2>{{ $editingId?'Editar':'Nova' }} verificação</h2><button class="btn btn-secondary" wire:click="$set('wizard',false)" wire:confirm="Descartar configuração não salva?">Fechar</button></header>
<p class="step">Passo {{ $step }} de 5 · {{ [1=>'Escopo',2=>'Condições',3=>'Frequência',4=>'Alertas',5=>'Testar'][$step] }}</p>
@foreach($errors->all() as $error)<p class="error">{{ $error }}</p>@endforeach
@if($step===1)
<label>Nome<input wire:model="name"></label>
<label>Loja<select wire:model="targetStore"><option value="">Selecione</option>@foreach($stores as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></label>
<label>Nível<select wire:model="level">@foreach(['SERVER','APP','API','SITE'] as $v)<option>{{ $v }}</option>@endforeach</select></label>
<label>Tipo<select wire:model.live="checkType">@foreach(\App\Services\Checks\CheckConfiguration::TYPES as $v)<option>{{ $v }}</option>@endforeach</select></label>
@elseif($step===2)
@php $names=['url'=>'URL ou caminho relativo','method'=>'Método HTTP','expected_status'=>'Status esperado','text'=>'Texto ou regex com delimitadores','mode'=>'Presença / ausência','regex'=>'Usar regex','expected'=>'Diretivas esperadas','require_explicit'=>'Exigir meta robots explícito','allow_disallow_all'=>'Permitir bloqueio geral','require_sitemap'=>'Exigir Sitemap no robots.txt','min_urls'=>'Mínimo de URLs','warn_ms'=>'Atenção acima de (ms)','critical_ms'=>'Crítico acima de (ms)','stale_minutes'=>'Máximo sem execução (min)','running_minutes'=>'Máximo em running (min)','max_pending'=>'Limite de pendentes','max_missed'=>'Limite de missed','max_errors'=>'Limite de erros','follow_redirects'=>'Seguir redirecionamentos']; @endphp
@foreach($configuration as $key=>$value)
@if($key!=='timeout')<label>{{ $names[$key]??$key }}
@if(is_bool($value))<input type="checkbox" wire:model="configuration.{{ $key }}">
@elseif($key==='method')<select wire:model="configuration.method"><option>GET</option><option>HEAD</option></select>
@elseif($key==='mode')<select wire:model="configuration.mode"><option value="present">Deve conter</option><option value="absent">Não deve conter</option></select>
@elseif($key==='expected')<select wire:model="configuration.expected">@foreach(['index,follow','noindex,follow','index,nofollow','noindex,nofollow'] as $v)<option>{{ $v }}</option>@endforeach</select>
@else<input wire:model="configuration.{{ $key }}" type="{{ is_int($value)?'number':'text' }}">@endif</label>@endif
@endforeach
@if($checkType==='API_HEALTH')<p>Usa as credenciais da loja em uma consulta de leitura.</p>@endif
@if($checkType==='MAGENTO_CRON')<p>Exige agente e conexão de banco somente leitura configurados no servidor Magento.</p>@endif
@elseif($step===3)
<label>Intervalo<select wire:model="interval">@foreach(\App\Services\Checks\CheckConfiguration::INTERVALS as $v)<option value="{{ $v }}">{{ $v/60 }} minutos</option>@endforeach</select></label>
@if(array_key_exists('timeout',$configuration))<label>Timeout (segundos)<input type="number" wire:model="configuration.timeout" min="1" max="30"></label>@endif
<label>Falhas consecutivas antes do alerta<input type="number" min="1" wire:model="failures"></label>
<label>Cooldown (segundos)<input type="number" min="0" wire:model="cooldown"></label>
@elseif($step===4)
<label><input type="checkbox" wire:model="notifyFailure"> Notificar falhas</label><label><input type="checkbox" wire:model="notifyRecovery"> Notificar recuperação</label>
<label>Severidade<select wire:model="severity"><option>WARNING</option><option>CRITICAL</option></select></label>
<p>Envios usam os destinatários configurados pelo administrador. Estados UNKNOWN ficam no histórico, sem simular uma falha do alvo.</p>
@else
<p>Teste assíncrono, sem histórico permanente e sem envio de alertas.</p><button class="btn btn-secondary" wire:click="testCheck" wire:loading.attr="disabled">Testar verificação</button>
@if($testResult)<p class="badge badge-{{ $testResult['status'] }}">{{ $testResult['status'] }}</p><pre>{{ json_encode($testResult,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>@endif
@endif
<div class="toolbar">@if($step>1)<button class="btn btn-secondary" wire:click="previousStep">Voltar</button>@endif
@if($step<5)<button class="btn btn-primary" wire:click="nextStep">Continuar</button>@else<button class="btn btn-primary" wire:click="save">Salvar verificação</button>@endif</div>
</aside>@endif
@if($selected)
<div class="drawer-backdrop"></div><aside class="drawer" role="dialog" aria-modal="true" aria-label="Histórico da verificação">
<header><h2>{{ $selected->name }}</h2><button class="btn btn-secondary" wire:click="closeSelected">Fechar</button></header>
<p>{{ $selected->store->name }} · {{ $selected->level }} · {{ $selected->check_type }}</p><span class="badge badge-{{ $selected->last_status }}">{{ $selected->last_status }}</span>
<p>Duração média das últimas execuções: {{ round($selected->runs->avg('duration_ms')??0) }} ms · Falhas consecutivas: {{ $selected->failure_count }}</p>
<div class="toolbar"><button class="btn btn-primary" wire:click="runNow({{ $selected->id }})">Executar agora</button>@can('manage')<button class="btn btn-secondary" wire:click="toggle({{ $selected->id }})">{{ $selected->enabled?'Desabilitar':'Habilitar' }}</button><button class="btn btn-secondary" wire:click="edit({{ $selected->id }})">Editar</button>@endcan</div>
<details><summary>Configuração</summary><pre>{{ json_encode($selected->configuration,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>
<h3>Histórico recente</h3>@forelse($selected->runs as $run)<details class="panel"><summary>{{ $run->started_at }} · {{ $run->status }} · {{ $run->duration_ms }} ms {{ $run->manual?'· Manual':'' }}</summary><p>{{ $run->summary }}</p><pre>{{ json_encode(['metrics'=>$run->metrics,'details'=>$run->details,'error'=>$run->error_message],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>@empty<p>Ainda sem execuções.</p>@endforelse
<h3>Entregas de alertas</h3>@foreach($selected->alerts as $alert)<p>{{ $alert->created_at }} · {{ $alert->to_status }} · {{ $alert->sent_at?'Enviado':($alert->error??'Na fila') }}</p>@endforeach
</aside>@endif
</div>
