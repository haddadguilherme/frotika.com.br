@extends('layouts.app')

@section('title', 'CT-e | Frotika')

@php
    $discountFor = fn ($document): float => 100 - (float) $document->getAttribute('applied_share_percent');
    $totalDiscount = $totals['total_value_cents'] > 0
        ? ($totals['total_value_cents'] - $totals['net_value_cents']) / $totals['total_value_cents'] * 100
        : 0.0;
@endphp

@section('content')
    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <h1 class="font-display text-xl font-semibold text-slate-900">CT-e</h1>
            <p class="mt-0.5 text-sm text-slate-500">
                {{ Format::date($filters['from']) }} — {{ Format::date($filters['to']) }}
                · {{ $documents->count() }} {{ \Illuminate\Support\Str::plural('documento', $documents->count()) }}
            </p>
        </div>

        @if ($canImport)
            <div class="hidden lg:block">
                <x-ui.link-button href="{{ route('cte.import') }}" variant="primary">Importar CT-e</x-ui.link-button>
            </div>
        @endif
    </div>

    <form method="GET" action="{{ route('cte.index') }}"
        class="mb-4 grid gap-2 rounded-lg border border-slate-200 bg-white p-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
        <div>
            <label for="from" class="text-2xs font-semibold uppercase tracking-wide text-slate-500">Data do serviço — de</label>
            <input id="from" type="date" name="from" value="{{ $filters['from'] }}"
                class="mt-1 h-9 w-full rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20" />
        </div>
        <div>
            <label for="to" class="text-2xs font-semibold uppercase tracking-wide text-slate-500">até</label>
            <input id="to" type="date" name="to" value="{{ $filters['to'] }}"
                class="mt-1 h-9 w-full rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20" />
        </div>
        <div class="flex gap-2 sm:col-span-2 lg:col-span-2">
            <x-ui.button type="submit" class="w-full">Aplicar</x-ui.button>
            <x-ui.link-button href="{{ route('cte.index') }}" variant="secondary" class="w-full justify-center">Mês atual</x-ui.link-button>
        </div>
    </form>

    <div class="rounded-lg border border-slate-200 bg-white">
        <div class="hidden max-h-[calc(100vh-16rem)] overflow-auto md:block">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50">
                    <tr class="border-b border-slate-200">
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">CT-e</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Emissão</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Remetente / Destinatário</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Veículo</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Trecho</th>
                        <th class="px-3 py-2 text-right text-2xs font-semibold uppercase tracking-wide text-slate-500">Peso</th>
                        <th class="px-3 py-2 text-right text-2xs font-semibold uppercase tracking-wide text-slate-500">Prestação</th>
                        <th class="px-3 py-2 text-right text-2xs font-semibold uppercase tracking-wide text-slate-500">Desc.</th>
                        <th class="px-3 py-2 text-right text-2xs font-semibold uppercase tracking-wide text-slate-500">Líquido</th>
                        <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="w-10 px-3 py-2 text-center text-2xs font-semibold uppercase tracking-wide text-slate-500">XML</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        @php $discount = $discountFor($document); @endphp
                        <tr class="h-9 border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3">
                                <a href="{{ route('cte.show', ['cte' => $document->getKey()]) }}"
                                    class="font-medium text-slate-900 hover:text-brand-700">
                                    {{ $document->getAttribute('number') }}/{{ $document->getAttribute('series') }}
                                </a>
                            </td>
                            <td class="px-3 text-slate-600">{{ Format::date($document->issued_at) }}</td>
                            <td class="px-3 py-1.5">
                                <div class="leading-tight">
                                    <span class="block truncate text-slate-900">{{ $document->getAttribute('sender_name') ?? '—' }}</span>
                                    <span class="block truncate text-xs text-slate-500">{{ $document->getAttribute('recipient_name') ?? '—' }}</span>
                                </div>
                            </td>
                            <td class="px-3 font-mono text-xs text-slate-600 tabular">
                                {{ $document->vehicle?->getAttribute('plate') ? Format::plate($document->vehicle->getAttribute('plate')) : '—' }}
                            </td>
                            <td class="px-3 py-1.5">
                                <div class="leading-tight">
                                    <span class="block text-slate-700">
                                        {{ collect([$document->getAttribute('origin_city'), $document->getAttribute('origin_state')])->filter()->join('/') ?: '—' }}
                                    </span>
                                    <span class="block text-xs text-slate-500">
                                        → {{ collect([$document->getAttribute('destination_city'), $document->getAttribute('destination_state')])->filter()->join('/') ?: '—' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 text-right font-mono text-slate-700 tabular">{{ Format::weight($document->getAttribute('cargo_weight_kg')) }}</td>
                            <td class="px-3 text-right font-mono tabular text-slate-900">
                                <span class="unit">R$</span> {{ Format::moneyDecimal($document->getAttribute('total_value_cents') / 100) }}
                            </td>
                            <td @class([
                                'px-3 text-right font-mono tabular',
                                'text-slate-400' => $discount <= 0,
                                'text-slate-700' => $discount > 0,
                            ])>{{ Format::percent($discount, $discount == (int) $discount ? 0 : 1) }}</td>
                            <td class="px-3 text-right font-mono tabular text-slate-900">
                                <span class="unit">R$</span> {{ Format::moneyDecimal($document->getAttribute('receivable_value_cents') / 100) }}
                            </td>
                            <td class="px-3">
                                <span class="text-xs text-slate-500">{{ $document->status->label() }}</span>
                            </td>
                            <td class="px-3 text-center">
                                @if ($document->getAttribute('xml_path'))
                                    <a href="{{ route('cte.xml', ['cte' => $document->getKey()]) }}"
                                        class="inline-flex text-slate-400 hover:text-brand-700" title="Baixar XML"
                                        aria-label="Baixar XML do CT-e {{ $document->getAttribute('number') }}">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                            <path d="M12 3v12m0 0 4-4m-4 4-4-4" stroke-linecap="round" stroke-linejoin="round" />
                                            <path d="M4 17v2a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-2" stroke-linecap="round" />
                                        </svg>
                                    </a>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11">
                                <div class="px-4 py-12 text-center">
                                    <p class="font-display text-lg font-semibold text-slate-900">Nenhum CT-e no período.</p>
                                    <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                                        Ajuste o filtro de datas ou envie o XML dos seus CT-e para o sistema cadastrar os
                                        parceiros, vincular o veículo e lançar a receita automaticamente.
                                    </p>
                                    @if ($canImport)
                                        <div class="mt-4 flex justify-center">
                                            <x-ui.link-button href="{{ route('cte.import') }}" variant="primary">Importar CT-e</x-ui.link-button>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($documents->isNotEmpty())
                    <tfoot class="sticky bottom-0 bg-slate-50">
                        <tr class="h-9 border-t border-slate-300">
                            <td class="px-3 text-2xs uppercase tracking-wide text-slate-500" colspan="5">Total do período</td>
                            <td class="px-3 text-right font-mono text-slate-700 tabular">{{ Format::weight($totals['weight_kg']) }}</td>
                            <td class="px-3 text-right font-mono font-medium tabular text-slate-900">
                                <span class="unit">R$</span> {{ Format::moneyDecimal($totals['total_value_cents'] / 100) }}
                            </td>
                            <td class="px-3 text-right font-mono text-slate-500 tabular">{{ Format::percent($totalDiscount, $totalDiscount == (int) $totalDiscount ? 0 : 1) }}</td>
                            <td class="px-3 text-right font-mono font-medium tabular text-slate-900">
                                <span class="unit">R$</span> {{ Format::moneyDecimal($totals['net_value_cents'] / 100) }}
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <div class="divide-y divide-slate-100 md:hidden">
            @forelse ($documents as $document)
                @php $discount = $discountFor($document); @endphp
                <div class="px-4 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <a href="{{ route('cte.show', ['cte' => $document->getKey()]) }}" class="font-medium text-slate-900">
                            CT-e {{ $document->getAttribute('number') }}/{{ $document->getAttribute('series') }}
                        </a>
                        <span class="font-mono text-sm tabular text-slate-900">
                            <span class="unit">R$</span> {{ Format::moneyDecimal($document->getAttribute('receivable_value_cents') / 100) }}
                        </span>
                    </div>
                    <div class="mt-1 leading-tight">
                        <span class="block truncate text-sm text-slate-700">{{ $document->getAttribute('sender_name') ?? '—' }}</span>
                        <span class="block truncate text-xs text-slate-500">→ {{ $document->getAttribute('recipient_name') ?? '—' }}</span>
                    </div>
                    <div class="mt-1 text-xs text-slate-500">
                        {{ collect([$document->getAttribute('origin_city'), $document->getAttribute('origin_state')])->filter()->join('/') ?: '—' }}
                        →
                        {{ collect([$document->getAttribute('destination_city'), $document->getAttribute('destination_state')])->filter()->join('/') ?: '—' }}
                    </div>
                    <div class="mt-1 flex items-center justify-between gap-3 text-xs text-slate-500">
                        <span>{{ Format::date($document->issued_at) }} · {{ Format::weight($document->getAttribute('cargo_weight_kg')) }} · prestação R$ {{ Format::moneyDecimal($document->getAttribute('total_value_cents') / 100) }} · desc. {{ Format::percent($discount, $discount == (int) $discount ? 0 : 1) }}</span>
                        @if ($document->getAttribute('xml_path'))
                            <a href="{{ route('cte.xml', ['cte' => $document->getKey()]) }}" class="shrink-0 font-medium text-brand-700">XML</a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-4 py-12 text-center">
                    <p class="font-display text-lg font-semibold text-slate-900">Nenhum CT-e no período.</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">Ajuste o filtro de datas ou importe novos XMLs.</p>
                </div>
            @endforelse
        </div>
    </div>

    @if ($canImport)
        <a href="{{ route('cte.import') }}"
            class="fixed bottom-20 right-4 z-20 flex size-14 items-center justify-center rounded-full bg-brand-700 text-white active:bg-brand-800 md:hidden shadow-overlay"
            aria-label="Importar CT-e">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="M12 5v14M5 12h14" stroke-linecap="round" />
            </svg>
        </a>
    @endif
@endsection
