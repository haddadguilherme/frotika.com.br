@extends('layouts.app')

@section('title', 'Lançamentos | Frotika')

@php
    use App\Domain\Finance\Enums\FinancialEntryStatus;
    use App\Domain\Finance\Enums\FinancialEntryType;

    $statusChip = fn ($status) => match ($status) {
        FinancialEntryStatus::Settled => 'border-success-300 bg-success-50 text-success-700',
        FinancialEntryStatus::Forecast => 'border-warning-300 bg-warning-50 text-warning-700',
        default => 'border-slate-300 bg-slate-50 text-slate-400 line-through',
    };
@endphp

@section('content')
    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <h1 class="font-display text-xl font-semibold text-slate-900">Lançamentos</h1>
            <p class="mt-0.5 text-sm text-slate-500">{{ $entries->total() }} {{ \Illuminate\Support\Str::plural('lançamento', $entries->total()) }}</p>
        </div>
        @if ($canManage)
            <div class="hidden lg:block">
                <x-ui.link-button href="{{ route('financial-entries.create') }}" variant="primary">Novo lançamento</x-ui.link-button>
            </div>
        @endif
    </div>

    <form method="GET" action="{{ route('financial-entries.index') }}"
        class="mb-3 grid gap-2 rounded-lg border border-slate-200 bg-white p-3 sm:grid-cols-2 lg:grid-cols-6">
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Descrição…"
            class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 lg:col-span-2" />

        <select name="type" class="h-9 rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-900">
            <option value="">Tipo: todos</option>
            <option value="revenue" @selected($filters['type'] === 'revenue')>Receita</option>
            <option value="expense" @selected($filters['type'] === 'expense')>Despesa</option>
        </select>

        <select name="status" class="h-9 rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-900">
            <option value="">Situação: todas</option>
            <option value="forecast" @selected($filters['status'] === 'forecast')>Previsto</option>
            <option value="settled" @selected($filters['status'] === 'settled')>Liquidado</option>
            <option value="canceled" @selected($filters['status'] === 'canceled')>Cancelado</option>
        </select>

        <select name="account" class="h-9 rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-900">
            <option value="">Conta: todas</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->getKey() }}" @selected($filters['account'] === (int) $account->getKey())>{{ $account->getAttribute('name') }}</option>
            @endforeach
        </select>

        <select name="vehicle" class="h-9 rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-900">
            <option value="">Veículo: todos</option>
            @foreach ($vehicles as $vehicle)
                <option value="{{ $vehicle->getKey() }}" @selected($filters['vehicle'] === (int) $vehicle->getKey())>{{ Format::plate($vehicle->getAttribute('plate')) }}</option>
            @endforeach
        </select>

        <div class="flex gap-2 lg:col-span-2">
            <input type="date" name="from" value="{{ $filters['from'] }}" class="h-9 w-full rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-900" />
            <input type="date" name="to" value="{{ $filters['to'] }}" class="h-9 w-full rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-900" />
        </div>

        <select name="category" class="h-9 rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-900 lg:col-span-2">
            <option value="">Categoria: todas</option>
            @foreach ($categories as $category)
                <option value="{{ $category->getKey() }}" @selected($filters['category'] === (int) $category->getKey())>{{ $category->getAttribute('code') }} — {{ $category->getAttribute('name') }}</option>
            @endforeach
        </select>

        <div class="flex gap-2 lg:col-span-2">
            <x-ui.button type="submit" class="w-full">Filtrar</x-ui.button>
            <x-ui.link-button href="{{ route('financial-entries.index') }}" variant="secondary" class="w-full justify-center">Limpar</x-ui.link-button>
        </div>
    </form>

    <div class="rounded-lg border border-slate-200 bg-white">
        <div class="hidden overflow-auto md:block">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Competência</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Descrição</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Categoria</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Situação</th>
                        <th class="px-3 py-2 text-right text-2xs font-semibold uppercase tracking-wide text-slate-500">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        @php
                            $amountCents = (int) $entry->getAttribute('amount_cents');
                            $signed = $entry->getAttribute('type') === FinancialEntryType::Expense ? -$amountCents : $amountCents;
                            $canceled = $entry->getAttribute('status') === FinancialEntryStatus::Canceled;
                        @endphp
                        <tr class="h-9 cursor-pointer border-b border-slate-100 hover:bg-slate-50"
                            onclick="window.location='{{ route('financial-entries.show', ['entry' => $entry->getKey()]) }}'">
                            <td class="px-3 font-mono tabular text-slate-600">{{ Format::date($entry->getAttribute('competence_date')) }}</td>
                            <td class="max-w-xs truncate px-3 text-slate-900">{{ $entry->getAttribute('description') }}</td>
                            <td class="px-3 font-mono text-xs tabular text-slate-500">{{ $entry->category?->getAttribute('code') }}</td>
                            <td class="px-3"><span class="inline-flex items-center rounded-full border px-2 py-0.5 text-2xs font-semibold {{ $statusChip($entry->getAttribute('status')) }}">{{ $entry->getAttribute('status')->label() }}</span></td>
                            <td @class([
                                'px-3 text-right font-mono tabular',
                                'text-slate-400' => $canceled,
                                'text-danger-700' => ! $canceled && $signed < 0,
                                'text-slate-900' => ! $canceled && $signed >= 0,
                            ])>{{ Format::money($signed) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="px-4 py-12 text-center">
                                    <p class="font-display text-lg font-semibold text-slate-900">Nenhum lançamento encontrado.</p>
                                    <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">Importe CT-e para gerar receitas automaticamente ou registre um lançamento manual.</p>
                                    @if ($canManage)
                                        <div class="mt-4 flex justify-center">
                                            <x-ui.link-button href="{{ route('financial-entries.create') }}" variant="primary">Novo lançamento</x-ui.link-button>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($entries->isNotEmpty())
                    <tfoot class="sticky bottom-0 bg-slate-50">
                        <tr class="border-t border-slate-200 text-sm">
                            <td colspan="4" class="px-3 py-2 text-right text-2xs font-semibold uppercase tracking-wide text-slate-500">
                                Receitas {{ Format::money($totals['revenue_cents']) }} · Despesas {{ Format::money(-$totals['expense_cents']) }} · Resultado
                            </td>
                            <td @class([
                                'px-3 py-2 text-right font-mono font-semibold tabular',
                                'text-danger-700' => $totals['net_cents'] < 0,
                                'text-slate-900' => $totals['net_cents'] >= 0,
                            ])>{{ Format::money($totals['net_cents']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <div class="divide-y divide-slate-100 md:hidden">
            @forelse ($entries as $entry)
                @php
                    $amountCents = (int) $entry->getAttribute('amount_cents');
                    $signed = $entry->getAttribute('type') === FinancialEntryType::Expense ? -$amountCents : $amountCents;
                @endphp
                <a href="{{ route('financial-entries.show', ['entry' => $entry->getKey()]) }}" class="block px-4 py-3 active:bg-slate-50">
                    <div class="flex items-center justify-between gap-3">
                        <span class="truncate text-slate-900">{{ $entry->getAttribute('description') }}</span>
                        <span @class([
                            'shrink-0 font-mono tabular text-sm',
                            'text-danger-700' => $signed < 0,
                            'text-slate-900' => $signed >= 0,
                        ])>{{ Format::money($signed) }}</span>
                    </div>
                    <div class="mt-0.5 flex items-center gap-2 text-xs text-slate-500">
                        <span class="font-mono tabular">{{ Format::date($entry->getAttribute('competence_date')) }}</span>
                        <span class="inline-flex items-center rounded-full border px-1.5 py-0.5 text-2xs font-semibold {{ $statusChip($entry->getAttribute('status')) }}">{{ $entry->getAttribute('status')->label() }}</span>
                    </div>
                </a>
            @empty
                <div class="px-4 py-12 text-center">
                    <p class="font-display text-lg font-semibold text-slate-900">Nenhum lançamento encontrado.</p>
                </div>
            @endforelse
        </div>
    </div>

    <div class="mt-4">
        {{ $entries->links() }}
    </div>

    @if ($canManage)
        <a href="{{ route('financial-entries.create') }}"
            class="fixed bottom-20 right-4 z-20 flex size-14 items-center justify-center rounded-full bg-brand-700 text-white shadow-overlay active:bg-brand-800 md:hidden"
            aria-label="Novo lançamento">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="M12 5v14M5 12h14" stroke-linecap="round" />
            </svg>
        </a>
    @endif
@endsection
