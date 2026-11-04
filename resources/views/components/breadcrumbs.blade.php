@props(['store' => null, 'title' => null])
@php
    $currentLabel = match (request()->route()?->getName()) {
        'stores.index' => 'Lojas',
        'stores.create' => 'Adicionar loja',
        'stores.edit' => 'Configurações',
        'stores.overview' => 'Visão geral',
        'stores.products' => 'Produtos',
        'stores.orders' => 'Pedidos',
        'stores.api-logs' => 'Logs de API',
        'stores.diagnostics' => 'Diagnóstico',
        'stores.tools' => 'Ferramentas',
        'stores.checks', 'stores.check-definitions' => 'Verificação',
        'stores.bulk-templates' => 'Modelos de edição',
        'login' => 'Entrar',
        default => $title ?? 'Operação',
    };
@endphp
<nav class="breadcrumbs" aria-label="Caminho de navegação">
    <ol>
        <li><a wire:navigate href="{{ route('stores.index') }}" class="breadcrumbs__home"><x-brand-logo symbol/><span>BeaconHub</span></a></li>
        @if($store instanceof \App\Models\Store)
            <li><x-icon name="chevron-right" class="breadcrumbs__separator"/><a wire:navigate href="{{ route('stores.overview', $store) }}" class="breadcrumbs__store" title="{{ $store->name }}">{{ $store->name }}</a></li>
        @endif
        <li><x-icon name="chevron-right" class="breadcrumbs__separator"/><span class="breadcrumbs__current" aria-current="page">{{ $currentLabel }}</span></li>
    </ol>
</nav>
