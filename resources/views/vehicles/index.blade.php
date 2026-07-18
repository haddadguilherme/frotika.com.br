@extends('layouts.app')

@section('title', 'Veículos | Frotika')

@php
    $statusChip = fn (\App\Domain\Fleet\Enums\VehicleStatus $status) => match ($status) {
        \App\Domain\Fleet\Enums\VehicleStatus::Active => 'border-success-300 bg-success-50 text-success-700',
        \App\Domain\Fleet\Enums\VehicleStatus::Maintenance => 'border-warning-300 bg-warning-50 text-warning-700',
        default => 'border-slate-300 bg-slate-50 text-slate-500',
    };
@endphp

@section('content')
    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <h1 class="font-display text-xl font-semibold text-slate-900">Veículos</h1>
            <p class="mt-0.5 text-sm text-slate-500">
                {{ $vehicles->count() }} {{ \Illuminate\Support\Str::plural('veículo', $vehicles->count()) }} cadastrado{{ $vehicles->count() === 1 ? '' : 's' }}
            </p>
        </div>

        @if ($canManage)
            <div class="hidden lg:block">
                <x-ui.link-button href="{{ route('vehicles.create') }}" variant="primary">Novo veículo</x-ui.link-button>
            </div>
        @endif
    </div>

    <div class="rounded-lg border border-slate-200 bg-white">
        <div class="hidden overflow-auto md:block">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Placa</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Tipo</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Marca / Modelo</th>
                        <th class="px-3 py-2 text-right text-2xs font-semibold uppercase tracking-wide text-slate-500">Ano</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Propriedade</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vehicles as $vehicle)
                        <tr class="h-9 border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3">
                                <a href="{{ route('vehicles.show', ['vehicle' => $vehicle->getKey()]) }}"
                                    class="font-mono font-medium tabular text-slate-900 hover:text-brand-700">{{ Format::plate($vehicle->getAttribute('plate')) }}</a>
                                @if ($vehicle->getAttribute('provisioned'))
                                    <span class="ml-1 inline-flex items-center rounded-full border border-warning-300 bg-warning-50 px-2 py-0.5 text-2xs font-semibold text-warning-700">Provisionado</span>
                                @endif
                            </td>
                            <td class="px-3 text-slate-600">{{ $vehicle->type->label() }}</td>
                            <td class="px-3 text-slate-600">{{ collect([$vehicle->getAttribute('brand'), $vehicle->getAttribute('model')])->filter()->join(' ') ?: '—' }}</td>
                            <td class="px-3 text-right font-mono tabular text-slate-600">{{ $vehicle->getAttribute('year_model') ?: '—' }}</td>
                            <td class="px-3 text-slate-600">{{ $vehicle->ownership->label() }}</td>
                            <td class="px-3">
                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-2xs font-semibold {{ $statusChip($vehicle->status) }}">{{ $vehicle->status->label() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="px-4 py-12 text-center">
                                    <p class="font-display text-lg font-semibold text-slate-900">Nenhum veículo cadastrado.</p>
                                    <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                                        Os veículos são criados automaticamente ao importar CT-e (pela placa), ou cadastrados manualmente aqui.
                                    </p>
                                    @if ($canManage)
                                        <div class="mt-4 flex justify-center">
                                            <x-ui.link-button href="{{ route('vehicles.create') }}" variant="primary">Novo veículo</x-ui.link-button>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-slate-100 md:hidden">
            @forelse ($vehicles as $vehicle)
                <a href="{{ route('vehicles.show', ['vehicle' => $vehicle->getKey()]) }}" class="block px-4 py-3 active:bg-slate-50">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-mono font-medium tabular text-slate-900">{{ Format::plate($vehicle->getAttribute('plate')) }}</span>
                        <span class="text-xs text-slate-500">{{ $vehicle->type->label() }}</span>
                    </div>
                    <div class="mt-0.5 text-xs text-slate-500">
                        {{ collect([$vehicle->getAttribute('brand'), $vehicle->getAttribute('model')])->filter()->join(' ') ?: 'Sem marca/modelo' }}
                    </div>
                </a>
            @empty
                <div class="px-4 py-12 text-center">
                    <p class="font-display text-lg font-semibold text-slate-900">Nenhum veículo cadastrado.</p>
                </div>
            @endforelse
        </div>
    </div>

    @if ($canManage)
        <a href="{{ route('vehicles.create') }}"
            class="fixed bottom-20 right-4 z-20 flex size-14 items-center justify-center rounded-full bg-brand-700 text-white active:bg-brand-800 md:hidden shadow-overlay"
            aria-label="Novo veículo">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="M12 5v14M5 12h14" stroke-linecap="round" />
            </svg>
        </a>
    @endif
@endsection
