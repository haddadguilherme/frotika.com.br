@extends('layouts.app')

@section('title', Format::plate($vehicle->getAttribute('plate')) . ' | Frotika')

@php
    $statusChip = match ($vehicle->status) {
        \App\Domain\Fleet\Enums\VehicleStatus::Active => 'border-success-300 bg-success-50 text-success-700',
        \App\Domain\Fleet\Enums\VehicleStatus::Maintenance => 'border-warning-300 bg-warning-50 text-warning-700',
        default => 'border-slate-300 bg-slate-50 text-slate-500',
    };
@endphp

@section('content')
    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('vehicles.index') }}" class="text-sm text-slate-500 hover:text-brand-700">Veículos</a>
                <span class="text-slate-300">/</span>
                <h1 class="font-display text-xl font-semibold tabular text-slate-900">{{ Format::plate($vehicle->getAttribute('plate')) }}</h1>
            </div>
            <div class="mt-1 flex items-center gap-2">
                <p class="text-sm text-slate-500">{{ $vehicle->type->label() }}</p>
                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-2xs font-semibold {{ $statusChip }}">{{ $vehicle->status->label() }}</span>
                @if ($vehicle->getAttribute('provisioned'))
                    <span class="inline-flex items-center rounded-full border border-warning-300 bg-warning-50 px-2 py-0.5 text-2xs font-semibold text-warning-700">Provisionado</span>
                @endif
            </div>
        </div>

        @if ($canManage)
            <div class="flex items-center gap-2">
                <x-ui.link-button href="{{ route('vehicles.edit', ['vehicle' => $vehicle->getKey()]) }}" variant="secondary">Editar</x-ui.link-button>
                <form method="POST" action="{{ route('vehicles.destroy', ['vehicle' => $vehicle->getKey()]) }}"
                    onsubmit="return confirm('Desativar este veículo?');">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="danger">Desativar</x-ui.button>
                </form>
            </div>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Identificação</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Marca / Modelo</dt>
                    <dd class="text-slate-900">{{ collect([$vehicle->getAttribute('brand'), $vehicle->getAttribute('model')])->filter()->join(' ') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Ano fab. / modelo</dt>
                    <dd class="font-mono tabular text-slate-900">{{ collect([$vehicle->getAttribute('year_manufacture'), $vehicle->getAttribute('year_model')])->filter()->join(' / ') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Propriedade</dt>
                    <dd class="text-slate-900">{{ $vehicle->ownership->label() }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">RNTRC</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $vehicle->getAttribute('rntrc') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">RENAVAM</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $vehicle->getAttribute('renavam') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Chassi</dt>
                    <dd class="font-mono text-slate-900">{{ $vehicle->getAttribute('chassis') ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Especificações</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Carroceria</dt>
                    <dd class="text-slate-900">{{ $vehicle->body_type?->label() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Combustível</dt>
                    <dd class="text-slate-900">{{ $vehicle->fuel_type?->label() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Eixos</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $vehicle->getAttribute('axles') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Tanque</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $vehicle->getAttribute('tank_capacity_l') ? $vehicle->getAttribute('tank_capacity_l') . ' L' : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Tara</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $vehicle->getAttribute('tare_kg') ? $vehicle->getAttribute('tare_kg') . ' kg' : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Capacidade</dt>
                    <dd class="font-mono tabular text-slate-900">
                        {{ collect([
                            $vehicle->getAttribute('capacity_kg') ? $vehicle->getAttribute('capacity_kg') . ' kg' : null,
                            $vehicle->getAttribute('capacity_m3') ? rtrim(rtrim((string) $vehicle->getAttribute('capacity_m3'), '0'), '.') . ' m³' : null,
                        ])->filter()->join(' · ') ?: '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Hodômetro</dt>
                    <dd class="font-mono tabular text-slate-900">{{ Format::km((int) $vehicle->getAttribute('odometer_current')) }} km</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 lg:col-span-2">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Financeiro e depreciação</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-4">
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Aquisição</dt>
                    <dd class="text-slate-900">{{ $vehicle->getAttribute('acquisition_date') ? Format::date($vehicle->getAttribute('acquisition_date')) : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Valor de aquisição</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $vehicle->getAttribute('acquisition_value_cents') !== null ? Format::money((int) $vehicle->getAttribute('acquisition_value_cents')) : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Valor residual</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $vehicle->getAttribute('residual_value_cents') !== null ? Format::money((int) $vehicle->getAttribute('residual_value_cents')) : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Depreciação</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $vehicle->getAttribute('depreciation_months') ? $vehicle->getAttribute('depreciation_months') . ' meses' : '—' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    @if ($vehicle->getAttribute('notes'))
        <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-2 text-sm font-semibold text-slate-900">Observações</h2>
            <p class="whitespace-pre-line text-sm text-slate-700">{{ $vehicle->getAttribute('notes') }}</p>
        </div>
    @endif
@endsection
