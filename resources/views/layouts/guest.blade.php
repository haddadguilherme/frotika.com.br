<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title', 'Frotika')</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>

<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute inset-x-0 top-0 h-96 bg-linear-to-b from-brand-950 via-brand-900 to-brand-700"></div>
        <div class="absolute inset-x-0 top-72 h-104 bg-linear-to-b from-brand-100/60 to-slate-100"></div>
        <div class="absolute -left-16 top-24 h-48 w-48 rounded-full bg-accent-500/20 blur-3xl"></div>
        <div class="absolute -right-16 top-32 h-56 w-56 rounded-full bg-info-700/20 blur-3xl"></div>
    </div>

    <header class="border-b border-white/20 backdrop-blur-sm">
        <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('welcome') }}" class="inline-flex items-center gap-3">
                <span
                    class="inline-flex h-9 w-9 items-center justify-center rounded-md bg-accent-500 text-sm font-display font-semibold text-brand-950">
                    F
                </span>
                <span class="font-display text-lg font-semibold text-white">Frotika</span>
            </a>

            <nav class="flex items-center gap-2">
                @auth
                    <x-ui.link-button href="{{ route('dashboard') }}" variant="secondary" size="sm">
                        Ir para o painel
                    </x-ui.link-button>
                @else
                    <x-ui.link-button href="{{ route('login') }}" variant="ghost" size="sm"
                        class="text-white hover:bg-white/10">
                        Entrar
                    </x-ui.link-button>
                    <x-ui.link-button href="{{ route('register') }}" variant="secondary" size="sm">
                        Criar conta
                    </x-ui.link-button>
                @endauth
            </nav>
        </div>
    </header>

    <main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
        @if (session('status'))
            <x-ui.card class="mb-6 border-success-700/30 bg-success-700/10 p-4">
                <p class="text-sm font-medium text-success-700">{{ session('status') }}</p>
            </x-ui.card>
        @endif

        @yield('content')
    </main>
</body>

</html>
