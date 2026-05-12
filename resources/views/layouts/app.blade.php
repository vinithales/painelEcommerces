<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'BringPainel' }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body>
        <div class="shell">
            {{-- Sidebar --}}
            <aside class="sidebar">
                <div class="sidebar__brand">
                    <h1>BringPainel</h1>
                    <p>Management Backoffice</p>
                </div>

                <a href="{{ route('stores.create') }}" wire:navigate class="sidebar__new-btn">
                    <span class="material-symbols-outlined" style="font-size:18px">add</span>
                    New Integration
                </a>

                <nav class="sidebar__nav">
                    <a href="{{ route('stores.index') }}" wire:navigate
                       class="sidebar__link {{ request()->routeIs('stores.index') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">storefront</span>
                        Store Hub
                    </a>
                    <a href="{{ route('stores.checks') }}" wire:navigate
                       class="sidebar__link {{ request()->routeIs('stores.checks') ? 'active' : '' }}">
                        <span class="material-symbols-outlined">sync_problem</span>
                        Integration Checks
                    </a>
                </nav>

                <div class="sidebar__footer">
                    <a href="{{ route('stores.create') }}" wire:navigate class="sidebar__link">
                        <span class="material-symbols-outlined">add_circle</span>
                        Add Store
                    </a>
                </div>
            </aside>

            {{-- Main --}}
            <div class="main-content">
                <header class="topbar">
                    <nav class="topbar__nav">
                        <a href="{{ route('stores.index') }}" wire:navigate
                           class="topbar__link {{ request()->routeIs('stores.index') ? 'active' : '' }}">
                            Dashboard
                        </a>
                        <a href="{{ route('stores.checks') }}" wire:navigate
                           class="topbar__link {{ request()->routeIs('stores.checks') ? 'active' : '' }}">
                            Checks
                        </a>
                    </nav>
                    <div class="topbar__actions">
                        <button class="topbar__icon-btn" title="Notifications">
                            <span class="material-symbols-outlined">notifications</span>
                        </button>
                        <button class="topbar__icon-btn" title="Settings">
                            <span class="material-symbols-outlined">settings</span>
                        </button>
                    </div>
                </header>

                <main class="page">
                    @if (session('status'))
                        <div class="flash">
                            {{ session('status') }}
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>

        @livewireScripts
        @stack('scripts')
    </body>
</html>