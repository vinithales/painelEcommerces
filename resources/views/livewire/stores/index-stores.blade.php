<div>
<div class="page-header"><div><h2>Lojas</h2><p>Operação, integrações e saúde das suas lojas.</p></div>@can('configure')<a wire:navigate href="{{ route('stores.create') }}" class="btn btn-primary">Adicionar loja</a>@endcan</div>
@include('livewire.stores.partials.dashboard')
<div class="toolbar"><label>Buscar<input type="search" wire:model.live.debounce.300ms="search" placeholder="Nome, domínio ou prefixo"></label>
<label>Plataforma<select wire:model.live="platform"><option value="all">Todas</option><option value="woocommerce">WooCommerce</option><option value="magento2">Magento 2</option></select></label></div>
<div class="card table-wrap"><table class="data-table"><thead><tr><th>Loja</th><th>Plataforma / gateways</th><th>API</th><th>Servidor</th><th>Falhas</th><th>Última execução</th><th>Ações</th></tr></thead><tbody>
@forelse($stores as $store)
@php
$active=$store->checkAssignments->where('enabled',true);
$api=$active->filter(fn ($assignment) => $assignment->checkDefinition?->level === 'API')->sortByDesc(fn ($assignment) => $assignment->latestExecution?->created_at)->first()?->healthStatus()??'UNKNOWN';
$server=($store->serverConnection || $store->getRawOriginal('ssh_profile') || $store->hasSshPasswordConnection())?($active->filter(fn ($assignment) => $assignment->checkDefinition?->level === 'SERVER')->sortByDesc(fn ($assignment) => $assignment->latestExecution?->created_at)->first()?->healthStatus()??'UNKNOWN'):'Não configurado';
$lastExecution=$active->pluck('latestExecution')->filter()->sortByDesc('created_at')->first();
@endphp
<tr wire:key="store-{{ $store->id }}"><td><a wire:navigate href="{{ route('stores.overview',$store) }}"><strong>{{ $store->name }}</strong></a><p class="muted">{{ parse_url($store->store_url,PHP_URL_HOST) }}</p></td>
<td><span class="platform-pill platform-pill--{{ $store->platform }}">{{ $store->isMagento2()?'Magento 2':'WooCommerce' }}</span><p>{{ $store->payment_gateway??'—' }} · {{ $store->shipping_gateway??'—' }}</p></td>
<td><span class="badge badge-{{ $api }}">{{ $api }}</span></td><td><span class="badge badge-{{ $server }}">{{ $server }}</span></td><td>{{ $active->filter(fn ($assignment) => $assignment->healthStatus() === 'CRITICAL')->count() }}</td><td>{{ $lastExecution?->created_at?->format('d/m/Y H:i') ?? 'Ainda sem execuções' }}</td>
<td>@include('livewire.stores.partials.actions')</td></tr>
@empty<tr><td colspan="7" class="empty">Nenhuma loja encontrada.</td></tr>@endforelse
</tbody></table></div>{{ $stores->links() }}
</div>
