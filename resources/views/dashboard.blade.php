@extends('layouts.app')

@section('title', 'Painel | Frotika')

@php
    use App\Domain\Fleet\Enums\VehicleType;

    $reais = fn (int $cents): string => Format::moneyDecimal($cents / 100);
    $dreUrl = fn (array $params = []) => route('dre.index', array_merge(['from' => $from, 'to' => $to], $params));

    $selectedId = $selected['vehicle_id'] ?? null;
@endphp

@section('content')
    <x-ui.page-header title="Painel operacional" subtitle="{{ $periodLabel }} · consolidado da frota">
        <x-slot:actions>
            {{-- <x-ui.button variant="secondary" size="sm">Importar CT-e</x-ui.button>
            <x-ui.button size="sm">Nova viagem</x-ui.button> --}}
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Faixa de instrumentos: um único card, separado por filete. Monocromático
         — só o resultado ganha sinal, não cor. --}}
    <section class="rounded-lg border border-slate-200 bg-white">
        <dl class="grid grid-cols-2 divide-slate-200 md:grid-cols-4 md:divide-x">
            <div class="border-b border-slate-200 p-4 md:border-b-0">
                <dt class="text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Saldo consolidado</dt>
                <dd class="mt-1 font-display text-2xl font-bold text-slate-900 tabular">
                    <span class="unit">R$</span> {{ $reais($kpis['balance_cents']) }}
                </dd>
                <p class="mt-1 text-xs text-slate-400">Caixa + bancos</p>
            </div>
            <div class="border-b border-slate-200 p-4 md:border-b-0">
                <dt class="text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Receita do mês</dt>
                <dd class="mt-1 font-display text-2xl font-bold text-slate-900 tabular">
                    <span class="unit">R$</span> {{ $reais($kpis['revenue_cents']) }}
                </dd>
                <p class="mt-1 text-xs text-slate-400">Competência atual</p>
            </div>
            <div class="border-b border-slate-200 p-4 md:border-b-0">
                <dt class="text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Custos do mês</dt>
                <dd class="mt-1 font-display text-2xl font-bold text-slate-900 tabular">
                    <span class="unit">R$</span> {{ $reais($kpis['costs_cents']) }}
                </dd>
                <p class="mt-1 text-xs text-slate-400">Operação + administrativo</p>
            </div>
            <div class="p-4">
                <dt class="text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Resultado projetado</dt>
                <dd @class([
                    'mt-1 font-display text-2xl font-bold tabular',
                    'text-danger-700' => $kpis['result_cents'] < 0,
                    'text-slate-900' => $kpis['result_cents'] >= 0,
                ])>{{ Format::money($kpis['result_cents'], true) }}</dd>
                <p class="mt-1 text-xs text-slate-400">Com previstos até o fim do mês</p>
            </div>
        </dl>
    </section>

    <section class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1fr)_480px]">
        {{-- Comparativo da frota: o pior primeiro. É o momento "aha". --}}
        <div class="min-w-0 rounded-lg border border-slate-200 bg-white">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-2.5">
                <div>
                    <h2 class="font-display text-lg font-semibold text-slate-900">Comparativo da frota</h2>
                    <p class="text-xs text-slate-400">Ordenado por resultado · o pior primeiro</p>
                </div>
                <x-ui.link-button :href="$dreUrl()" variant="ghost" size="sm">Ver DRE</x-ui.link-button>
            </div>

            @if ($vehicles === [])
                <div class="px-4 py-12 text-center">
                    <p class="text-sm text-slate-500">Nenhum veículo com movimento neste mês.</p>
                    <p class="mt-1 text-xs text-slate-400">Lance abastecimentos, viagens ou despesas para ver o comparativo.</p>
                </div>
            @else
                <div class="max-h-[calc(100vh-22rem)] overflow-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-slate-50">
                            <tr class="border-b border-slate-200">
                                <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Veículo</th>
                                <th class="w-24 px-3 py-2 text-right text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">km</th>
                                <th class="w-20 px-3 py-2 text-right text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">km/l</th>
                                <th class="w-64 px-3 py-2 text-left text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">R$/km</th>
                                <th class="w-32 px-3 py-2 text-right text-2xs font-semibold uppercase tracking-[0.12em] text-slate-500">Resultado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vehicles as $row)
                                @php $result = (int) $row['metrics']['net_result_cents']; @endphp
                                <tr onclick="window.location.href='{{ $dreUrl(['vehicle' => $row['vehicle_id']]) }}'"
                                    @class([
                                        'h-9 cursor-pointer border-b border-slate-100 hover:bg-slate-50',
                                        'bg-brand-50 shadow-[inset_2px_0_0_0_var(--color-brand-700)]' => $row['vehicle_id'] === $selectedId,
                                    ])>
                                    <td class="px-3"><x-ui.plate-chip :plate="$row['plate']" :type="$row['type']" /></td>
                                    <td class="px-3 text-right font-mono text-slate-600 tabular">{{ $row['km'] > 0 ? Format::moneyDecimal($row['km'], 0) : '—' }}</td>
                                    <td class="px-3 text-right font-mono text-slate-600 tabular">{{ Format::consumption($row['consumption']) }}</td>
                                    <td class="px-3 py-1.5">
                                        @if ($row['km'] > 0)
                                            <x-ui.km-gauge :revenue="$row['per_km']['revenue']" :cost="$row['per_km']['cost']" :breakeven="$row['per_km']['breakeven']" compact />
                                        @else
                                            <span class="text-2xs text-slate-400">sem km no período</span>
                                        @endif
                                    </td>
                                    <td @class([
                                        'px-3 text-right font-mono font-medium tabular',
                                        'text-danger-700' => $result < 0,
                                        'text-slate-900' => $result >= 0,
                                    ])>{{ Format::money($result, true) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="sticky bottom-0 bg-slate-50">
                            <tr class="h-9 border-t border-slate-300">
                                <td class="px-3 text-2xs uppercase tracking-[0.12em] text-slate-500">Média da frota</td>
                                <td class="px-3 text-right font-mono text-slate-700 tabular">{{ $totals['km'] > 0 ? Format::moneyDecimal($totals['km'], 0) : '—' }}</td>
                                <td class="px-3 text-right font-mono text-slate-700 tabular">{{ Format::consumption($totals['consumption']) }}</td>
                                <td class="px-3 text-right font-mono text-slate-500 tabular">{{ $totals['km'] > 0 ? 'R$/km '.Format::moneyDecimal($totals['per_km']['cost']) : '—' }}</td>
                                <td @class([
                                    'px-3 text-right font-mono font-medium tabular',
                                    'text-danger-700' => $totals['net_result_cents'] < 0,
                                    'text-slate-900' => $totals['net_result_cents'] >= 0,
                                ])>{{ Format::money((int) $totals['net_result_cents'], true) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>

        {{-- Master-detail: o veículo selecionado. A régua é o acento da tela. --}}
        <div class="rounded-lg border border-slate-200 bg-white">
            @if ($selected === null)
                <div class="flex h-full items-center justify-center px-4 py-12 text-center">
                    <p class="text-sm text-slate-500">Selecione um veículo no comparativo para ver o detalhe.</p>
                </div>
            @else
                @php
                    $typeLabel = $selected['type'] ? VehicleType::from($selected['type'])->label() : null;
                    $sm = $selected['metrics'];
                    $selResult = (int) $sm['net_result_cents'];
                @endphp
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-2.5">
                    <div class="flex items-center gap-2">
                        <x-ui.plate-chip :plate="$selected['plate']" :type="$selected['type']" />
                        <div>
                            <p class="font-display text-sm font-semibold text-slate-900">{{ Format::plate($selected['plate']) }}</p>
                            <p class="text-xs text-slate-400">{{ $typeLabel ? $typeLabel.' · ' : '' }}{{ $periodLabel }}</p>
                        </div>
                    </div>
                    <x-ui.link-button :href="$dreUrl(['vehicle' => $selected['vehicle_id']])" variant="ghost" size="sm">Abrir DRE</x-ui.link-button>
                </div>

                <div class="p-4">
                    @if ($selected['km'] > 0)
                        <x-ui.km-gauge :revenue="$selected['per_km']['revenue']" :cost="$selected['per_km']['cost']" :breakeven="$selected['per_km']['breakeven']" />
                    @else
                        <p class="text-sm text-slate-500">Sem km no período — a régua de R$/km fica indisponível.</p>
                    @endif

                    <dl class="mt-4 space-y-0 text-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 py-2">
                            <dt class="text-slate-500">Km rodados</dt>
                            <dd class="font-mono text-slate-900 tabular">{{ $selected['km'] > 0 ? Format::km($selected['km']) : '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between border-b border-slate-100 py-2">
                            <dt class="text-slate-500">Receita líquida</dt>
                            <dd class="font-mono text-slate-900 tabular">{{ Format::money((int) $sm['net_revenue_cents']) }}</dd>
                        </div>
                        <div class="flex items-center justify-between border-b border-slate-100 py-2">
                            <dt class="text-slate-500">Custos variáveis</dt>
                            <dd class="font-mono text-slate-900 tabular">{{ Format::money((int) $sm['variable_cost_cents']) }}</dd>
                        </div>
                        <div class="flex items-center justify-between border-b border-slate-100 py-2">
                            <dt class="text-slate-500">Custos fixos</dt>
                            <dd class="font-mono text-slate-900 tabular">{{ Format::money((int) $sm['fixed_cost_cents']) }}</dd>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <dt class="font-medium text-slate-700">Resultado líquido</dt>
                            <dd @class([
                                'font-display text-lg font-semibold tabular',
                                'text-danger-700' => $selResult < 0,
                                'text-success-700' => $selResult >= 0,
                            ])>{{ Format::money($selResult, true) }}</dd>
                        </div>
                    </dl>
                </div>
            @endif
        </div>
    </section>
@endsection
