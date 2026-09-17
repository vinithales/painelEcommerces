<div>
<div class="page-header"><div><h2>{{ $resource==='product'?'Produtos':'Pedidos' }}</h2><p>{{ $store->name }} · {{ $store->platform }}</p></div><a wire:navigate class="btn btn-secondary" href="{{ route('stores.api-logs',$store) }}">Histórico de API</a></div>
@if($rollbackMessage)<div class="flash {{ $rollbackFailed ? 'flash--error' : '' }}" role="{{ $rollbackFailed ? 'alert' : 'status' }}">{{ $rollbackMessage }}</div>@endif
@foreach($errors->all() as $error)<div class="error" role="alert">{{ $error }}</div>@endforeach
<form wire:submit="applyFilters" class="panel">
<div class="field-grid">
@if($resource==='product')
<label>Nome<input wire:model="filters.name" placeholder="Buscar produto"></label>
<label>SKU exato<input wire:model="filters.sku"></label>
<label>Prefixo de SKU<input wire:model="filters.sku_prefix"></label>
@else
<label>Número / ID<input wire:model="filters.number"></label><label>E-mail<input wire:model="filters.customer_email" type="email"></label>
@endif
<label>Status<input wire:model="filters.status" placeholder="Todos"></label>
<label>De<input type="date" wire:model="filters.date_from"></label><label>Até<input type="date" wire:model="filters.date_to"></label>
<label>Por página<select wire:model="perPage"><option>25</option><option>50</option><option>100</option></select></label>
@if($resource==='product')<label>Ordenar<select wire:model="filters.sort"><option value="">Padrão</option><option value="name">Nome</option><option value="price">Preço</option><option value="id">ID</option></select></label><label>Direção<select wire:model="filters.direction"><option value="asc">Crescente</option><option value="desc">Decrescente</option></select></label>@endif
</div><button class="btn btn-primary" wire:loading.attr="disabled">Aplicar filtros</button>
@if($resource==='product' && $store->isWooCommerce())<p class="muted">O prefixo de SKU filtra a página remota atual. Use SKU exato para localizar diretamente. A contagem corresponde à consulta remota.</p>@endif
</form>
<div class="toolbar"><button class="btn btn-secondary" wire:click="refreshRows" wire:loading.attr="disabled">Atualizar página</button>
@can('manage')<button class="btn btn-secondary" data-bulk-select>Editar selecionados</button><a wire:navigate href="{{ route('stores.bulk-templates',$store) }}">Templates</a>@endcan
<span wire:loading role="status">Consultando plataforma…</span></div>
<div wire:ignore data-resource-grid="{{ $resource }}" style="height:560px"></div>
<div class="toolbar"><button wire:click="movePage(-1)" class="btn btn-secondary" @disabled($page<=1)>Anterior</button><span>Página {{ $page }} · {{ $total }} registros</span><button wire:click="movePage(1)" class="btn btn-secondary" @disabled($page*$perPage >= $total)>Próxima</button></div>
@if($resource==='product' && $snapshots->isNotEmpty())
<details class="panel"><summary>Snapshots e rollback</summary><table class="data-table"><thead><tr><th>Produto</th><th>Data</th><th>Ação</th></tr></thead><tbody>
@foreach($snapshots as $snapshot)<tr><td>{{ $snapshot->resource_id }}</td><td>{{ $snapshot->created_at }}</td><td>@can('manage')<button wire:click="rollback({{ $snapshot->id }})" wire:confirm="Reaplicar os valores anteriores deste snapshot?" class="btn btn-secondary">Rollback</button>@endcan</td></tr>@endforeach
</tbody></table></details>@endif
@if($selectedKey)
<div class="drawer-backdrop"></div>
<aside class="drawer" role="dialog" aria-modal="true" aria-label="Detalhes" x-data x-init="$nextTick(() => $el.querySelector('button').focus())" @keydown.escape="if(confirm('Fechar e descartar alterações não salvas?')) $wire.closeDetail()" >
<header><h2>{{ $detail['name'] ?? 'Pedido '.($detail['number']??$selectedKey) }}</h2><button class="btn btn-secondary" wire:click="closeDetail" wire:confirm="Fechar e descartar alterações não salvas?">Fechar</button></header>
@foreach($errors->all() as $error)<div class="error">{{ $error }}</div>@endforeach
<div class="toolbar">
<button class="btn btn-secondary" wire:click="openDetail(@js($selectedKey))" wire:confirm="Atualizar e descartar alterações locais?">Atualizar via API</button>
<button class="btn btn-secondary" x-on:click="navigator.clipboard.writeText(@js((string)($detail['id']??'')))">Copiar ID</button>
@if($resource==='product')<button class="btn btn-secondary" x-on:click="navigator.clipboard.writeText(@js($detail['sku']??''))">Copiar SKU</button>
@if($detail['url']??null)<a class="btn btn-secondary" href="{{ $detail['url'] }}" target="_blank" rel="noopener noreferrer">Abrir na loja</a>@endif @endif
</div>
@php
$sections=$resource==='product'?[
'Geral'=>['id','name','sku','type','status','visibility','description','short_description','url'],
'Preço'=>['regular_price','sale_price','price','currency'],'Estoque'=>['stock_quantity','stock_status','manage_stock'],
'Físico'=>['weight','dimensions'],'Organização'=>['categories','attributes','variations']
]:['Resumo'=>['number','id','created_at','state','status','currency','total'],'Cliente'=>['customer','customer_email','phone'],
'Itens'=>['items'],'Valores'=>['subtotal','discount','shipping_total','tax','total'],
'Pagamento'=>['payment_method','payment_status','transaction_id'],'Entrega'=>['shipping_method','shipping_address','tracking']];
$labels=['id'=>'ID','name'=>'Nome','sku'=>'SKU','type'=>'Tipo','status'=>'Status','visibility'=>'Visibilidade','description'=>'Descrição','short_description'=>'Descrição curta','url'=>'URL','regular_price'=>'Preço regular','sale_price'=>'Promocional','price'=>'Preço atual','currency'=>'Moeda','stock_quantity'=>'Quantidade','stock_status'=>'Situação','manage_stock'=>'Gerenciar estoque','weight'=>'Peso','dimensions'=>'Dimensões','categories'=>'Categorias','attributes'=>'Atributos','variations'=>'Variações','number'=>'Número','created_at'=>'Data','state'=>'State Magento','customer'=>'Nome','customer_email'=>'E-mail','phone'=>'Telefone','items'=>'Itens','subtotal'=>'Subtotal','discount'=>'Desconto','shipping_total'=>'Frete','tax'=>'Impostos','total'=>'Total','payment_method'=>'Método','payment_status'=>'Pagamento informado','transaction_id'=>'Transação','shipping_method'=>'Método','shipping_address'=>'Endereço','tracking'=>'Rastreamento'];
@endphp
@foreach($sections as $title=>$fields)<section><h3>{{ $title }}</h3><dl class="detail-list">@foreach($fields as $field)<dt>{{ $labels[$field]??$field }}</dt><dd>@if(is_array($detail[$field]??null))<pre>{{ json_encode($detail[$field],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>@else{{ $detail[$field] ?? 'Não informado pela plataforma' }}@endif</dd>@endforeach</dl></section>@endforeach
@if($resource==='product')<section><h3>Mídia</h3><div class="gallery">@forelse($detail['gallery']??[] as $img)@if($img['src'])<img src="{{ $img['src'] }}" alt="{{ $img['alt'] }}">@endif @empty<p class="muted">Sem imagens.</p>@endforelse</div></section>@endif
@can('manage')
<section data-dirty><h3>Editar</h3><p class="muted">Somente campos alterados serão enviados.</p>
@if($resource==='product')<div class="field-grid">@foreach($editableFields as $field=>$rule)
<label>{{ $field==='price'?'Preço regular':($labels[$field]??$field) }}
@if(in_array($field,['description','short_description']))<textarea wire:model="draft.{{ $field }}"></textarea>
@elseif($field==='manage_stock')<input type="checkbox" wire:model="draft.{{ $field }}">
@else<input wire:model="draft.{{ $field }}">@endif</label>
@endforeach</div>
@else
<label>{{ $store->isMagento2()?'Operação Magento':'Novo status' }}<select wire:model="draft.{{ $store->isMagento2()?'operation':'status' }}"><option value="">Selecione</option>
@foreach($store->isMagento2()?['cancel','hold','unhold']:['pending','on-hold','processing','completed','cancelled'] as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach</select></label>
@endif
<div class="toolbar">@if($resource==='product')<button class="btn btn-secondary" wire:click="preview" wire:loading.attr="disabled">Pré-visualizar</button>@endif
<button class="btn btn-primary" wire:click="saveDetail" wire:confirm="Confirmar alteração na loja?" wire:loading.attr="disabled">Salvar</button>
<button class="btn btn-secondary" wire:click="openDetail(@js($selectedKey))" wire:confirm="Descartar alterações?">Descartar</button></div>
</section>@endcan
@include('livewire.stores.partials.preview')
<section><h3>Técnico</h3><details><summary>Payload original / última resposta</summary><pre>{{ json_encode($detail['raw']??[],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>
<h4>Histórico e última atualização</h4>@forelse($history as $log)<p>{{ $log->created_at }} · {{ $log->operation_type }} · {{ $log->status }}</p>@empty<p>Sem operações anteriores.</p>@endforelse</section>
</aside>@endif
@if($showBulkModal)<div class="drawer-backdrop"></div><aside class="drawer" role="dialog" aria-modal="true" aria-label="Edição em massa">
<header><h2>Editar {{ count($bulkSelectedIds) }} registros</h2><button class="btn btn-secondary" wire:click="$set('showBulkModal',false)">Fechar</button></header>
@error('bulk')<p class="error">{{ $message }}</p>@enderror
<label>Template<select wire:model="templateId"><option value="">Configuração manual</option>@foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach</select></label>
<label>Campo<select wire:model="bulkField">@foreach($resource==='product'?array_keys($editableFields):[$store->isMagento2()?'operation':'status'] as $field)<option>{{ $field }}</option>@endforeach</select></label>
<label>Novo valor<input wire:model="bulkValue"></label>
<div class="toolbar">@if($resource==='product')<button class="btn btn-secondary" wire:click="applyBulk(true)">Pré-visualizar</button>@endif<button class="btn btn-primary" wire:click="applyBulk" wire:confirm="Confirmar edição em massa na loja?" wire:loading.attr="disabled">Enfileirar edição</button></div>
@include('livewire.stores.partials.preview')
</aside>@endif
</div>
