@extends('layouts.app')

@section('title', 'Manutenção | Frotika')

@php
    use App\Domain\Finance\Enums\FinancialEntryStatus;
    use App\Domain\Maintenances\Enums\MaintenanceStatus;

    $statusChip = match ($maintenance->status) {
        MaintenanceStatus::Completed => 'border-success-300 bg-success-50 text-success-700',
        MaintenanceStatus::InProgress => 'border-warning-300 bg-warning-50 text-warning-700',
        MaintenanceStatus::Open => 'border-brand-200 bg-brand-50 text-brand-700',
        default => 'border-slate-300 bg-slate-50 text-slate-400',
    };

    $entryChip = fn ($status) => match ($status) {
        FinancialEntryStatus::Settled => 'border-success-300 bg-success-50 text-success-700',
        FinancialEntryStatus::Forecast => 'border-warning-300 bg-warning-50 text-warning-700',
        default => 'border-slate-300 bg-slate-50 text-slate-400',
    };
@endphp

@section('content')
    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('maintenances.index') }}" class="text-sm text-slate-500 hover:text-brand-700">Manutenções</a>
                <span class="text-slate-300">/</span>
                <h1 class="font-display text-xl font-semibold tabular text-slate-900">{{ Format::plate($maintenance->vehicle?->getAttribute('plate') ?? '—') }}</h1>
            </div>
            <div class="mt-1 flex items-center gap-2">
                <p class="text-sm text-slate-500">{{ $maintenance->type->label() }} · {{ $maintenance->category->label() }}</p>
                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-2xs font-semibold {{ $statusChip }}">{{ $maintenance->status->label() }}</span>
            </div>
        </div>

        @if ($canManage)
            <div class="flex items-center gap-2">
                <x-ui.link-button href="{{ route('maintenances.edit', ['maintenance' => $maintenance->getKey()]) }}" variant="secondary">Editar</x-ui.link-button>
                <form method="POST" action="{{ route('maintenances.destroy', ['maintenance' => $maintenance->getKey()]) }}"
                    onsubmit="return confirm('Excluir esta manutenção? A despesa vinculada será cancelada.');">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="danger">Excluir</x-ui.button>
                </form>
            </div>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Serviço</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Abertura</dt>
                    <dd class="font-mono tabular text-slate-900">{{ Format::date($maintenance->getAttribute('opened_at')) }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Conclusão</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $maintenance->getAttribute('closed_at') ? Format::date($maintenance->getAttribute('closed_at')) : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Odômetro</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $maintenance->getAttribute('odometer') !== null ? Format::km((int) $maintenance->getAttribute('odometer')) : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Oficina</dt>
                    <dd class="text-slate-900">
                        @if ($maintenance->supplier)
                            <a href="{{ route('partners.show', ['partner' => $maintenance->supplier->getKey()]) }}" class="font-medium text-brand-700 hover:text-brand-800">{{ $maintenance->supplier->getAttribute('trade_name') ?: $maintenance->supplier->getAttribute('legal_name') }}</a>
                        @else
                            {{ $maintenance->getAttribute('workshop_name') ?: '—' }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Horas paradas</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $maintenance->getAttribute('downtime_hours') !== null ? Format::moneyDecimal((float) $maintenance->getAttribute('downtime_hours'), 2).' h' : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Nota fiscal</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $maintenance->getAttribute('invoice_number') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Próx. revisão (km)</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $maintenance->getAttribute('next_service_odometer') !== null ? Format::km((int) $maintenance->getAttribute('next_service_odometer')) : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Próx. revisão (data)</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $maintenance->getAttribute('next_service_at') ? Format::date($maintenance->getAttribute('next_service_at')) : '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">Custos e financeiro</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Mão de obra</dt>
                    <dd class="font-mono tabular text-slate-900">{{ Format::money((int) $maintenance->getAttribute('labor_cents')) }}</dd>
                </div>
                <div>
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Peças</dt>
                    <dd class="font-mono tabular text-slate-900">{{ Format::money((int) $maintenance->getAttribute('parts_cents')) }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-2xs uppercase tracking-wide text-slate-400">Total</dt>
                    <dd class="font-mono tabular text-lg font-semibold text-slate-900">{{ Format::money((int) $maintenance->getAttribute('total_cents')) }}</dd>
                </div>

                @if ($entry !== null)
                    <div>
                        <dt class="text-2xs uppercase tracking-wide text-slate-400">Lançamento</dt>
                        <dd><span class="inline-flex items-center rounded-full border px-2 py-0.5 text-2xs font-semibold {{ $entryChip($entry->getAttribute('status')) }}">{{ $entry->getAttribute('status')->label() }}</span></dd>
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
                    <div class="col-span-2 text-sm text-slate-500">Nenhum lançamento financeiro vinculado (custo zero ou manutenção cancelada).</div>
                @endif
            </dl>
        </div>
    </div>

    @if ($maintenance->getAttribute('description') || $maintenance->getAttribute('notes'))
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            @if ($maintenance->getAttribute('description'))
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <h2 class="mb-2 text-sm font-semibold text-slate-900">Descrição do serviço</h2>
                    <p class="whitespace-pre-line text-sm text-slate-700">{{ $maintenance->getAttribute('description') }}</p>
                </div>
            @endif
            @if ($maintenance->getAttribute('notes'))
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <h2 class="mb-2 text-sm font-semibold text-slate-900">Observações</h2>
                    <p class="whitespace-pre-line text-sm text-slate-700">{{ $maintenance->getAttribute('notes') }}</p>
                </div>
            @endif
        </div>
    @endif
@endsection
