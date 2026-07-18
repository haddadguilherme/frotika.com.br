<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <title>@yield('title', 'Frotika')</title>
    <link rel="icon" type="image/png" href="{{ asset('icone-frotika.png') }}" />
    <link rel="apple-touch-icon" href="{{ asset('icone-frotika.png') }}" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>

<body class="flex min-h-screen flex-col bg-slate-100 font-sans text-slate-900 antialiased">
    <header class="bg-brand-950 border-b border-brand-800/70">
        <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('welcome') }}" class="inline-flex">
                <x-ui.brand on="dark" size="lg" />
            </a>

            <nav class="flex items-center gap-2">
                @auth
                    <x-ui.link-button href="{{ route('dashboard') }}" variant="secondary" size="sm">
                        Ir para o painel
                    </x-ui.link-button>
                @else
                    <x-ui.link-button href="{{ route('login') }}" variant="ghost" size="sm"
                        class="text-white hover:bg-brand-800/60 active:bg-brand-800/80">
                        Entrar
                    </x-ui.link-button>
                    <x-ui.link-button href="{{ route('register') }}" variant="secondary" size="sm">
                        Criar conta
                    </x-ui.link-button>
                @endauth
            </nav>
        </div>
    </header>

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        @if (session('status'))
            <x-ui.card class="mb-6 border-success-500/40 bg-success-50 p-4">
                <p class="text-sm font-medium text-success-700">{{ session('status') }}</p>
            </x-ui.card>
        @endif

        @yield('content')
    </main>

    <x-ui.developer-credits-footer />
</body>

</html>
