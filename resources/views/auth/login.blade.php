@extends('layouts.guest')

@section('title', 'Entrar | Frotika')

@section('content')
    <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-2 lg:items-center">
        <section>
            <p class="inline-flex items-center rounded-full bg-accent-500/20 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-950">
                DRE Veicular em minutos
            </p>
            <h1 class="mt-4 font-display text-3xl font-semibold text-white sm:text-4xl">Entrar no Frotika</h1>
            <p class="mt-3 max-w-lg text-sm text-brand-100 sm:text-base">
                Acompanhe receita, despesa e resultado por caminhao em uma rotina simples para o dia a dia da transportadora.
            </p>
        </section>

        <x-ui.card class="mx-auto w-full max-w-md">
            <h2 class="font-display text-2xl font-semibold text-slate-900">Acesso da empresa</h2>
            <p class="mt-2 text-sm text-slate-600">Use seu e-mail e senha para abrir o painel.</p>

            <form method="POST" action="{{ route('login.attempt') }}" class="mt-6 space-y-4">
                @csrf

                <x-ui.input
                    label="E-mail"
                    name="email"
                    type="email"
                    placeholder="voce@empresa.com.br"
                    autocomplete="email"
                    required
                />

                <x-ui.input
                    label="Senha"
                    name="password"
                    type="password"
                    placeholder="Sua senha"
                    autocomplete="current-password"
                    required
                />

                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500/30" @checked(old('remember')) />
                    Permanecer conectado
                </label>

                <x-ui.button type="submit" class="w-full justify-center">
                    Entrar
                </x-ui.button>
            </form>

            <p class="mt-4 text-center text-sm text-slate-600">
                Ainda nao tem acesso?
                <a href="{{ route('register') }}" class="font-medium text-brand-700 hover:text-brand-800">Criar conta agora</a>
            </p>
        </x-ui.card>
    </div>
@endsection
