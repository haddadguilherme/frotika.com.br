@extends('layouts.app')

@section('title', 'Painel | Frotika')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.card>
            <p class="text-sm font-medium text-slate-500">Saldo disponivel</p>
            <p class="mt-2 font-display text-2xl font-semibold tabular text-slate-900">R$ 0,00</p>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm font-medium text-slate-500">Receitas do mes</p>
            <p class="mt-2 font-display text-2xl font-semibold tabular text-success-700">R$ 0,00</p>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm font-medium text-slate-500">Despesas do mes</p>
            <p class="mt-2 font-display text-2xl font-semibold tabular text-danger-700">R$ 0,00</p>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm font-medium text-slate-500">Resultado projetado</p>
            <p class="mt-2 font-display text-2xl font-semibold tabular text-info-700">R$ 0,00</p>
        </x-ui.card>
    </div>

    <x-ui.card class="mt-6 border-brand-700/20 bg-brand-100/40">
        <h1 class="font-display text-2xl font-semibold text-brand-950">Base de layout pronta para as proximas telas</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-700">
            Esta e a estrutura inicial do app autenticado: sidebar, topbar e cards de KPI. A partir daqui, os modulos
            podem evoluir em Livewire sem retrabalho visual.
        </p>
    </x-ui.card>
@endsection
