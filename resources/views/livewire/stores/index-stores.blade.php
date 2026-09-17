<div>
<div class="page-header"><div><h2>Lojas</h2><p>Operação, integrações e saúde das suas lojas.</p></div>@can('configure')<a wire:navigate href="{{ route('stores.create') }}" class="btn btn-primary">Adicionar loja</a>@endcan</div>
<div class="toolbar"><label>Buscar<input type="search" wire:model.live.debounce.300ms="search" placeholder="Nome, domínio ou prefixo"></label>
<label>Plataforma<select wire:model.live="platform"><option value="all">Todas</option><option value="woocommerce">WooCommerce</option><option value="magento2">Magento 2</option></select></label><span class="muted">{{ $storeCount }} lojas · {{ $wooCommerceCount }} WooCommerce · {{ $magento2Count }} Magento</span></div>
<div class="card table-wrap"><table class="data-table"><thead><tr><th>Loja</th><th>Plataforma / gateways</th><th>API</th><th>Servidor</th><th>Alertas</th><th>Último check</th><th>Ações</th></tr></thead><tbody>
@forelse($stores as $store)
@php
$active=$store->checks->where('enabled',true);
$api=$active->where('level','API')->sortByDesc('last_run_at')->first()?->last_status??'UNKNOWN';
$server=$store->serverConnection?($active->where('level','SERVER')->sortByDesc('last_run_at')->first()?->last_status??'UNKNOWN'):'Não configurado';
@endphp
<tr wire:key="store-{{ $store->id }}"><td><a wire:navigate href="{{ route('stores.overview',$store) }}"><strong>{{ $store->name }}</strong></a><p class="muted">{{ parse_url($store->store_url,PHP_URL_HOST) }}</p></td>
<td><span class="platform-pill platform-pill--{{ $store->platform }}">{{ $store->isMagento2()?'Magento 2':'WooCommerce' }}</span><p>{{ $store->payment_gateway??'—' }} · {{ $store->shipping_gateway??'—' }}</p></td>
<td><span class="badge badge-{{ $api }}">{{ $api }}</span></td><td><span class="badge badge-{{ $server }}">{{ $server }}</span></td><td>{{ $active->whereNotNull('alert_status')->count() }}</td><td>{{ $active->max('last_run_at')??'Ainda sem checks' }}</td>
<td><details><summary>Ações</summary><div class="toolbar">@foreach(['products'=>'Produtos','orders'=>'Pedidos','api-logs'=>'Logs','diagnostics'=>'Diagnóstico','tools'=>'Ferramentas'] as $route=>$label)<a wire:navigate href="{{ route('stores.'.$route,$store) }}">{{ $label }}</a>@endforeach
<a wire:navigate href="{{ route('stores.checks',['storeId'=>$store->id]) }}">Verificações</a>
@can('configure')<a wire:navigate href="{{ route('stores.edit',$store) }}">Configurações</a><button class="btn btn-secondary" wire:click="delete({{ $store->id }})" wire:confirm="Excluir a loja e seu histórico local?">Excluir</button>@endcan
</div></details></td></tr>
@empty<tr><td colspan="7" class="empty">Nenhuma loja encontrada.</td></tr>@endforelse
</tbody></table></div>{{ $stores->links() }}
</div>
