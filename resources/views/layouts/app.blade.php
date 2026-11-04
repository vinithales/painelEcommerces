<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ isset($title) ? $title.' | BeaconHub' : 'BeaconHub' }}</title>
<link rel="icon" type="image/png" href="{{ asset('images/brand/beaconhub-symbol-light.png') }}" media="(prefers-color-scheme: light)">
<link rel="icon" type="image/png" href="{{ asset('images/brand/beaconhub-symbol-dark.png') }}" media="(prefers-color-scheme: dark)">
<script>try{document.documentElement.dataset.theme=localStorage.getItem('bring-theme')||(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');document.documentElement.dataset.sidebar=localStorage.getItem('bring-sidebar')||'open'}catch(e){}</script>
@vite(['resources/css/app.css','resources/js/app.js']) @livewireStyles</head>
<body><div class="shell">
@auth
<aside class="sidebar"><div class="sidebar__brand"><a wire:navigate href="{{ route('stores.index') }}" class="sidebar__logo" aria-label="BeaconHub ? in?cio"><x-brand-logo class="sidebar__logo-full"/><x-brand-logo symbol class="sidebar__logo-symbol"/></a><p>Operação e suporte</p></div>
<nav class="sidebar__nav" aria-label="Navegação principal">
<a wire:navigate href="{{ route('stores.index') }}" class="sidebar__link" title="Lojas"><x-icon name="store"/><span>Lojas</span></a>
<a wire:navigate href="{{ route('stores.checks') }}" class="sidebar__link" title="Verificação"><x-icon name="check"/><span>Verificação</span></a>
<a wire:navigate href="{{ route('stores.check-reports') }}" class="sidebar__link" title="Relatórios de verificações"><x-icon name="check"/><span>Relatórios de verificações</span></a>
@can('configure')<a wire:navigate href="{{ route('stores.create') }}" class="sidebar__link" title="Adicionar loja"><x-icon/><span>Adicionar loja</span></a>@endcan
@if(isset($store) && $store instanceof \App\Models\Store)
<p class="sidebar__section-label">{{ $store->name }}</p>
@foreach(['overview'=>'Visão geral','products'=>'Produtos','orders'=>'Pedidos','api-logs'=>'Logs de API','diagnostics'=>'Diagnóstico','tools'=>'Ferramentas'] as $path=>$label)
<a wire:navigate href="{{ route('stores.'.$path,$store) }}" class="sidebar__link" title="{{ $label }}"><x-icon :name="$path"/><span>{{ $label }}</span></a>
@endforeach
<a wire:navigate href="{{ route('stores.check-history', $store) }}" class="sidebar__link" title="Histórico de verificações"><x-icon name="check"/><span>Histórico de verificações</span></a>
@can('configure')<a wire:navigate href="{{ route('stores.edit',$store) }}" class="sidebar__link"><x-icon name="settings"/><span>Configurações</span></a>@endcan
@endif
</nav></aside>
@endauth
<div class="main-content" @guest style="margin-left:0" @endguest>
<header class="topbar"><div class="topbar__actions topbar__navigation"><button class="topbar__icon-btn" data-back-button data-back-fallback="{{ route('stores.index') }}" aria-label="Voltar" title="Voltar"><x-icon name="arrow-left"/></button>@auth<button class="topbar__icon-btn" data-sidebar-toggle aria-label="Alternar navegação"><x-icon name="menu"/></button>@endauth
<x-breadcrumbs :store="$store ?? null" :title="$title ?? null"/></div>
<div class="topbar__actions"><button data-theme-toggle class="topbar__icon-btn" aria-label="Alternar tema"><x-icon name="sun"/></button>
@auth<span>{{ auth()->user()->name }}</span><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-secondary">Sair</button></form>@endauth</div></header>
<main class="page">@if(session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif{{ $slot }}</main>
</div></div>@livewireScripts @stack('scripts')</body></html>
