@extends('layouts.app')

@section('title', $company->getAttribute('trade_name').' | Frotika')

@php
    $taxRegimeLabels = [
        'simples' => 'Simples Nacional',
        'presumido' => 'Lucro Presumido',
        'real' => 'Lucro Real',
    ];
    $address = collect([
        $company->getAttribute('street'),
        $company->getAttribute('number'),
        $company->getAttribute('complement'),
        $company->getAttribute('district'),
        collect([$company->getAttribute('city'), $company->getAttribute('state')])->filter()->join('/'),
        $company->getAttribute('zip_code'),
    ])->filter()->join(', ');
@endphp

@section('content')
    <div class="mx-auto max-w-3xl">
        <x-ui.page-header title="{{ $company->getAttribute('trade_name') }}"
            subtitle="{{ $company->getAttribute('legal_name') }}">
            <x-slot:actions>
                <x-ui.link-button href="{{ route('companies.index') }}" variant="secondary">Voltar</x-ui.link-button>
                @if ($canManage)
                    <x-ui.link-button href="{{ route('companies.edit', ['company' => $company->getKey()]) }}"
                        variant="primary">Editar</x-ui.link-button>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        @if ($errors->any())
            <div class="mb-4 rounded-md border border-danger-500/40 bg-danger-50 px-4 py-2.5 text-sm font-medium text-danger-700">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mb-3 flex flex-wrap items-center gap-2">
            @if ($isCurrent)
                <span class="inline-flex items-center rounded-full border border-accent-500/50 bg-accent-500/10 px-2 py-0.5 text-2xs font-semibold text-accent-700">Empresa ativa</span>
            @endif
            @if ($isPrimary)
                <span class="inline-flex items-center rounded-full border border-brand-300 bg-brand-50 px-2 py-0.5 text-2xs font-semibold text-brand-700">Principal do grupo</span>
            @endif
        </div>

        <div class="rounded-lg border border-slate-200 bg-white">
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between gap-4 px-4 py-2.5">
                    <dt class="text-slate-500">CNPJ</dt>
                    <dd class="font-mono tabular text-slate-900">{{ Format::cnpj($company->getAttribute('cnpj')) }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-4 py-2.5">
                    <dt class="text-slate-500">Regime tributário</dt>
                    <dd class="text-slate-900">{{ $taxRegimeLabels[$company->getAttribute('tax_regime')] ?? $company->getAttribute('tax_regime') }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-4 py-2.5">
                    <dt class="text-slate-500">Inscrição estadual</dt>
                    <dd class="text-slate-900">{{ $company->getAttribute('state_registration') ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-4 py-2.5">
                    <dt class="text-slate-500">RNTRC</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $company->getAttribute('rntrc') ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-4 py-2.5">
                    <dt class="text-slate-500">Endereço</dt>
                    <dd class="text-right text-slate-900">{{ $address ?: '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-4 py-2.5">
                    <dt class="text-slate-500">Telefone</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $company->getAttribute('phone') ? Format::phone($company->getAttribute('phone')) : '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-4 py-2.5">
                    <dt class="text-slate-500">E-mail</dt>
                    <dd class="text-slate-900">{{ $company->getAttribute('email') ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            @if (! $isCurrent)
                <form method="POST" action="{{ route('tenancy.switch-company') }}">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $company->getKey() }}" />
                    <x-ui.button type="submit" variant="secondary">Tornar empresa ativa</x-ui.button>
                </form>
            @endif

            @if ($canManage && ! $isPrimary && ! $isCurrent)
                <form method="POST" action="{{ route('companies.destroy', ['company' => $company->getKey()]) }}"
                    onsubmit="return confirm('Desativar esta empresa? Ela deixa de aparecer no seletor.');">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="danger">Desativar empresa</x-ui.button>
                </form>
            @endif
        </div>

        @if ($isPrimary)
            <p class="mt-2 text-xs text-slate-500">A empresa principal do grupo não pode ser desativada.</p>
        @elseif ($isCurrent)
            <p class="mt-2 text-xs text-slate-500">Troque a empresa ativa antes de desativar esta empresa.</p>
        @endif
    </div>
@endsection
