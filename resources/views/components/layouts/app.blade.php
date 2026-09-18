<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ isset($title) ? $title.' | BeaconHub' : 'BeaconHub' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body>
        <div class="shell">
            <header class="topbar">
                <a href="{{ route('stores.index') }}" wire:navigate class="brand">
                    <span>
                        <x-brand-logo/>
                    </span>
                </a>

                <nav class="topbar__nav">
                    <a href="{{ route('stores.index') }}" wire:navigate class="topbar__link">Stores</a>
                    <a href="{{ route('stores.create') }}" wire:navigate class="button button--ghost">Add store</a>
                </nav>
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

        @livewireScripts
    </body>
</html>
