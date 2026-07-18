@extends('layouts.app')

@section('title', 'Abastecimento | Frotika')

@php
    use App\Domain\Finance\Enums\FinancialEntryStatus;

    $statusChip = fn ($status) => match ($status) {
        FinancialEntryStatus::Settled => 'border-success-300 bg-success-50 text-success-700',
        FinancialEntryStatus::Forecast => 'border-warning-300 bg-warning-50 text-warning-700',
        default => 'border-slate-300 bg-slate-50 text-slate-400',
    };
@endphp

@section('content')
    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('fuelings.index') }}" class="text-sm text-slate-500 hover:text-brand-700">Abastecimentos</a>
                <span class="text-slate-300">/</span>
                <h1 class="font-display text-xl font-semibold tabular text-slate-900">{{ Format::plate($fueling->vehicle?->getAttribute('plate') ?? '—') }}</h1>
            </div>
            <p class="mt-1 text-sm text-slate-500">
                {{ Format::dateTime($fueling->getAttribute('fueled_at')) }} · {{ $fueling->product->label() }}
                @if ($fueling->getAttribute('full_tank'))
                    <span class="ml-1 inline-flex items-center rounded-full border border-brand-200 bg-brand-50 px-1.5 py-0.5 text-2xs font-semibold text-brand-700">tanque cheio</span>
                @endif
            </p>
        </div>

        @if ($canManage)
            <div class="flex items-center gap-2">
                <x-ui.link-button href="{{ route('fuelings.edit', ['fueling' => $fueling->getKey()]) }}" variant="secondary">Editar</x-ui.link-button>
                <form method="POST" action="{{ route('fuelings.destroy', ['fueling' => $fueling->getKey()]) }}"
                    onsubmit="return confirm('Excluir este abastecimento? A despesa vinculada será cancelada.');">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="danger">Excluir</x-ui.button>
                </form>
            </div>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Abastecimento</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Odômetro</dt>
                    <dd class="font-mono tabular text-slate-900">{{ Format::km((int) $fueling->getAttribute('odometer')) }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Tanque</dt>
                    <dd class="text-slate-900">{{ $fueling->tank->label() }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Litros</dt>
                    <dd class="font-mono tabular text-slate-900">{{ Format::liters((float) $fueling->getAttribute('liters')) }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Consumo</dt>
                    <dd class="font-mono tabular text-slate-900">{{ Format::consumption($fueling->getAttribute('km_per_liter') !== null ? (float) $fueling->getAttribute('km_per_liter') : null) }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Preço/litro</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $fueling->getAttribute('price_per_liter') !== null ? 'R$ '.Format::moneyDecimal((float) $fueling->getAttribute('price_per_liter'), 3) : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Total</dt>
                    <dd class="font-mono tabular font-semibold text-slate-900">{{ Format::money((int) $fueling->getAttribute('total_cents')) }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Pagamento</dt>
                    <dd class="text-slate-900">{{ $fueling->payment_method->label() }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Nota/cupom</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $fueling->getAttribute('invoice_number') ?: '—' }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Motorista</dt>
                    <dd class="text-slate-900">
                        @if ($fueling->driver)
                            <a href="{{ route('drivers.show', ['driver' => $fueling->driver->getKey()]) }}" class="font-medium text-brand-700 hover:text-brand-800">{{ $fueling->driver->getAttribute('name') }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Posto e financeiro</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div class="col-span-2">
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Posto</dt>
                    <dd class="text-slate-900">
                        @if ($fueling->station)
                            <a href="{{ route('partners.show', ['partner' => $fueling->station->getKey()]) }}" class="font-medium text-brand-700 hover:text-brand-800">{{ $fueling->station->getAttribute('trade_name') ?: $fueling->station->getAttribute('legal_name') }}</a>
                            <span class="text-slate-400">· cadastrado</span>
                        @else
                            {{ collect([
                                $fueling->getAttribute('station_name'),
                                collect([$fueling->getAttribute('station_city'), $fueling->getAttribute('station_state')])->filter()->join('/'),
                            ])->filter()->join(' · ') ?: '—' }}
                        @endif
                    </dd>
                </div>

                @if ($entry !== null)
                    <div>
                        <dt class="text-2xs uppercase tracking-wide text-slate-400">Lançamento</dt>
                        <dd>
                            <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-2xs font-semibold {{ $statusChip($entry->getAttribute('status')) }}">{{ $entry->getAttribute('status')->label() }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-2xs uppercase tracking-wide text-slate-400">Categoria</dt>
                        <dd class="font-mono text-xs tabular text-slate-600">{{ $entry->category?->getAttribute('code') }} — {{ $entry->category?->getAttribute('name') }}</dd>
                    </div>
                    <div class="col-span-2">
                        <a href="{{ route('financial-entries.show', ['entry' => $entry->getKey()]) }}"
                            class="text-sm font-medium text-brand-700 hover:text-brand-800">Ver lançamento no financeiro →</a>
                    </div>
                @else
                    <div class="col-span-2 text-sm text-slate-500">Nenhum lançamento financeiro vinculado.</div>
                @endif
            </dl>
        </div>
    </div>

    @if ($fueling->getAttribute('notes'))
        <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-2 text-sm font-semibold text-slate-900">Observações</h2>
            <p class="whitespace-pre-line text-sm text-slate-700">{{ $fueling->getAttribute('notes') }}</p>
        </div>
    @endif
@endsection
