@extends('layouts.guest')

@section('title', 'Frotika | DRE Veicular para transportadoras')

@section('content')
    <section class="grid gap-6 lg:grid-cols-[1.3fr_1fr] lg:items-start">
        <div>
            <p class="inline-flex items-center rounded-full bg-accent-500/20 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-950">
                Sistema para micro transportadoras
            </p>

            <h1 class="mt-4 max-w-3xl font-display text-4xl font-semibold leading-tight text-white sm:text-5xl">
                Descubra em poucos cliques se cada caminhao da lucro.
            </h1>

            <p class="mt-4 max-w-2xl text-base text-brand-100">
                O Frotika organiza operacao e financeiro em um painel unico para voce enxergar resultado por veiculo,
                agir rapido e proteger sua margem.
            </p>

            <div class="mt-6 flex flex-wrap gap-3">
                <x-ui.link-button href="{{ route('register') }}" class="shadow-lg shadow-brand-950/30">
                    Criar conta e comecar
                </x-ui.link-button>
                <x-ui.link-button href="{{ route('login') }}" variant="secondary">
                    Ja tenho acesso
                </x-ui.link-button>
            </div>
        </div>

        <x-ui.card class="bg-white/95">
            <h2 class="font-display text-xl font-semibold text-slate-900">Sem adivinhacao no caixa</h2>
            <p class="mt-2 text-sm text-slate-600">
                Feche o dia sabendo exatamente onde o dinheiro entrou, saiu e qual veiculo precisa de atencao.
            </p>

            <div class="mt-5 space-y-3 text-sm text-slate-700">
                <div class="flex items-start gap-2">
                    <span class="mt-1 h-2 w-2 rounded-full bg-success-700"></span>
                    <p>Fluxo de caixa com realizado e previsto.</p>
                </div>
                <div class="flex items-start gap-2">
                    <span class="mt-1 h-2 w-2 rounded-full bg-info-700"></span>
                    <p>DRE veicular para identificar lucro por caminhao.</p>
                </div>
                <div class="flex items-start gap-2">
                    <span class="mt-1 h-2 w-2 rounded-full bg-warning-700"></span>
                    <p>Cadastro simples para iniciar sem equipe de TI.</p>
                </div>
            </div>
        </x-ui.card>
    </section>

    <section class="mt-8 grid gap-4 md:grid-cols-3">
        <x-ui.card>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rotina</p>
            <h3 class="mt-2 font-display text-xl font-semibold text-slate-900">Lance em minutos</h3>
            <p class="mt-2 text-sm text-slate-600">
                Cadastre contas, lancamentos e recorrencias sem depender de planilhas quebradas.
            </p>
        </x-ui.card>

        <x-ui.card>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Controle</p>
            <h3 class="mt-2 font-display text-xl font-semibold text-slate-900">Compare contas</h3>
            <p class="mt-2 text-sm text-slate-600">
                Veja entradas e saidas por conta para agir antes de faltar caixa.
            </p>
        </x-ui.card>

        <x-ui.card>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Resultado</p>
            <h3 class="mt-2 font-display text-xl font-semibold text-slate-900">Priorize o que da retorno</h3>
            <p class="mt-2 text-sm text-slate-600">
                Use o painel para decidir onde investir e onde cortar custo na frota.
            </p>
        </x-ui.card>
    </section>
@endsection
