<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>@yield('title', 'Painel | Frotika')</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
        <div class="lg:grid lg:min-h-screen lg:grid-cols-[264px_1fr]">
            <aside class="hidden lg:flex lg:flex-col lg:justify-between lg:bg-linear-to-b lg:from-brand-950 lg:to-brand-900 lg:px-4 lg:py-5 lg:text-brand-100">
                <div>
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-2 py-1">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded bg-accent-500 font-display text-sm font-semibold text-brand-950">F</span>
                        <span class="font-display text-base font-semibold">Frotika</span>
                    </a>

                    <nav class="mt-8 space-y-6">
                        <section>
                            <p class="px-2 text-xs font-semibold uppercase tracking-[0.18em] text-brand-100/70">Operacao</p>
                            <ul class="mt-2 space-y-1">
                                <li><a href="#" class="block rounded-md px-2 py-2 text-sm text-white/90 hover:bg-brand-800/60">Painel</a></li>
                                <li><a href="#" class="block rounded-md px-2 py-2 text-sm text-brand-100 hover:bg-brand-800/60">Viagens</a></li>
                                <li><a href="#" class="block rounded-md px-2 py-2 text-sm text-brand-100 hover:bg-brand-800/60">Abastecimentos</a></li>
                            </ul>
                        </section>

                        <section>
                            <p class="px-2 text-xs font-semibold uppercase tracking-[0.18em] text-brand-100/70">Financeiro</p>
                            <ul class="mt-2 space-y-1">
                                <li><a href="#" class="block rounded-md px-2 py-2 text-sm text-brand-100 hover:bg-brand-800/60">Contas</a></li>
                                <li><a href="#" class="block rounded-md px-2 py-2 text-sm text-brand-100 hover:bg-brand-800/60">Lancamentos</a></li>
                                <li><a href="#" class="block rounded-md px-2 py-2 text-sm text-brand-100 hover:bg-brand-800/60">Fluxo de caixa</a></li>
                            </ul>
                        </section>
                    </nav>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="pt-6">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm" class="w-full justify-center">
                        Sair
                    </x-ui.button>
                </form>
            </aside>

            <div class="flex min-h-screen flex-col">
                <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 backdrop-blur-sm">
                    <div class="mx-auto flex h-16 w-full items-center justify-between px-4 sm:px-6 lg:px-8">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Atalhos rapidos</p>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <button type="button" class="rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100">+ Viagem</button>
                                <button type="button" class="rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100">+ Abastecimento</button>
                                <button type="button" class="rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100">+ Manutencao</button>
                                <button type="button" class="rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100">Importar CT-e</button>
                            </div>
                        </div>

                        <a href="{{ route('welcome') }}" class="text-sm font-medium text-brand-700 hover:text-brand-800">Site</a>
                    </div>
                </header>

                <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
