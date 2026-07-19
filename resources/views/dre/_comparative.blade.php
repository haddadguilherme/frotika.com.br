@php
    use App\Domain\Fleet\Enums\VehicleType;

    $totals = $dre['totals'];

    $sortUrl = function (string $key) use ($filters, $sort): string {
        $param = $sort['key'] === $key && $sort['dir'] === 'asc' ? '-' . $key : $key;

        return route('dre.index', [
            'from' => $filters['from'],
            'to' => $filters['to'],
            'sort' => $param,
        ]);
    };

    $arrow = function (string $key) use ($sort): string {
        if ($sort['key'] !== $key) {
            return '';
        }

        return $sort['dir'] === 'asc' ? ' ↑' : ' ↓';
    };

    $totalNet = $totals['net_result_cents'];
@endphp

<div class="rounded-lg border border-slate-200 bg-white">
    {{-- Desktop: a tabela que vende o produto --}}
    <div class="hidden overflow-auto lg:block">
        <table class="w-full text-sm">
            <thead class="sticky top-0 z-10 bg-slate-50">
                <tr class="border-b border-slate-200 text-2xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2 text-left">
                        <a href="{{ $sortUrl('plate') }}" class="hover:text-slate-900">Placa{{ $arrow('plate') }}</a>
                    </th>
                    <th class="px-3 py-2 text-left">Tipo</th>
                    <th class="w-24 px-3 py-2 text-right">
                        <a href="{{ $sortUrl('km') }}" class="hover:text-slate-900">km{{ $arrow('km') }}</a>
                    </th>
                    <th class="w-24 px-3 py-2 text-right">
                        <a href="{{ $sortUrl('revenue_km') }}" class="hover:text-slate-900">R$/km{{ $arrow('revenue_km') }}</a>
                    </th>
                    <th class="w-24 px-3 py-2 text-right">
                        <a href="{{ $sortUrl('cost_km') }}" class="hover:text-slate-900">Custo/km{{ $arrow('cost_km') }}</a>
                    </th>
                    <th class="w-20 px-3 py-2 text-right">
                        <a href="{{ $sortUrl('consumption') }}" class="hover:text-slate-900">km/l{{ $arrow('consumption') }}</a>
                    </th>
                    <th class="w-32 px-3 py-2 text-right">
                        <a href="{{ $sortUrl('net_result') }}" class="hover:text-slate-900">Result. caixa{{ $arrow('net_result') }}</a>
                    </th>
                    <th class="w-32 px-3 py-2 text-right">
                        <a href="{{ $sortUrl('economic_result') }}" class="hover:text-slate-900">Econômico{{ $arrow('economic_result') }}</a>
                    </th>
                    <th class="w-64 px-3 py-2 text-left">Resultado por km</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $net = $row['metrics']['net_result_cents'];
                        $economic = $row['economic_result_cents'];
                        $hasReserves = $row['reserves']['total_cents'] !== 0;
                        $href = route('dre.index', ['from' => $filters['from'], 'to' => $filters['to'], 'vehicle' => $row['vehicle_id']]);
                    @endphp
                    <tr class="h-9 cursor-pointer border-b border-slate-100 hover:bg-slate-50"
                        onclick="window.location='{{ $href }}'">
                        <td class="px-3">
                            <a href="{{ $href }}" onclick="event.stopPropagation()">
                                <x-ui.plate-chip :plate="$row['plate']" :type="$row['type']" />
                            </a>
                        </td>
                        <td class="px-3 text-slate-600">{{ $row['type'] ? VehicleType::from($row['type'])->label() : '—' }}</td>
                        <td class="px-3 text-right font-mono tabular text-slate-600">{{ $row['km'] > 0 ? Format::km($row['km']) : '—' }}</td>
                        <td class="px-3 text-right font-mono tabular text-slate-900">{{ $row['km'] > 0 ? Format::moneyDecimal($row['per_km']['revenue']) : '—' }}</td>
                        <td class="px-3 text-right font-mono tabular text-slate-600">{{ $row['km'] > 0 ? Format::moneyDecimal($row['per_km']['cost']) : '—' }}</td>
                        <td class="px-3 text-right font-mono tabular text-slate-600">{{ Format::consumption($row['consumption']) }}</td>
                        <td @class([
                            'px-3 text-right font-mono tabular font-medium',
                            'text-danger-700' => $net < 0,
                            'text-slate-900' => $net >= 0,
                        ])>{{ Format::money($net, true) }}</td>
                        <td @class([
                            'px-3 text-right font-mono tabular font-medium',
                            'text-danger-700' => $economic < 0,
                            'text-slate-900' => $economic >= 0,
                            'text-slate-400' => ! $hasReserves,
                        ])>{{ $hasReserves ? Format::money($economic, true) : '—' }}</td>
                        <td class="px-3">
                            @if ($row['km'] > 0)
                                <x-ui.km-gauge :revenue="$row['per_km']['revenue']" :cost="$row['per_km']['cost']"
                                    :breakeven="$row['per_km']['breakeven']" compact />
                            @else
                                <span class="text-2xs text-slate-400">sem km no período</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <div class="px-4 py-12 text-center">
                                <p class="font-display text-lg font-semibold text-slate-900">Nenhum veículo com movimento no período.</p>
                                <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">
                                    Importe CT-e e lance abastecimentos e manutenções — cada veículo aparece aqui com receita,
                                    custo e resultado por km.
                                </p>
                                <div class="mt-4 flex justify-center gap-2">
                                    <x-ui.link-button href="{{ route('cte.import') }}" variant="primary">Importar CT-e</x-ui.link-button>
                                    <x-ui.link-button href="{{ route('fuelings.create') }}" variant="secondary">Lançar abastecimento</x-ui.link-button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if (count($rows) > 0)
                <tfoot class="sticky bottom-0 z-10 bg-slate-50">
                    <tr class="border-t border-slate-300 font-display text-sm font-semibold">
                        <td class="px-3 py-2 text-slate-900" colspan="2">Frota · {{ $totals['vehicles_count'] }} {{ \Illuminate\Support\Str::plural('veículo', $totals['vehicles_count']) }}</td>
                        <td class="px-3 py-2 text-right font-mono tabular text-slate-700">{{ $totals['km'] > 0 ? Format::km($totals['km']) : '—' }}</td>
                        <td class="px-3 py-2 text-right font-mono tabular text-slate-900">{{ $totals['km'] > 0 ? Format::moneyDecimal($totals['per_km']['revenue']) : '—' }}</td>
                        <td class="px-3 py-2 text-right font-mono tabular text-slate-700">{{ $totals['km'] > 0 ? Format::moneyDecimal($totals['per_km']['cost']) : '—' }}</td>
                        <td class="px-3 py-2 text-right font-mono tabular text-slate-700">{{ Format::consumption($totals['consumption']) }}</td>
                        <td @class([
                            'px-3 py-2 text-right font-mono tabular',
                            'text-danger-700' => $totalNet < 0,
                            'text-slate-900' => $totalNet >= 0,
                        ])>{{ Format::money($totalNet, true) }}</td>
                        @php $totalEconomic = $totals['economic_result_cents']; @endphp
                        <td @class([
                            'px-3 py-2 text-right font-mono tabular',
                            'text-danger-700' => $totalEconomic < 0,
                            'text-slate-900' => $totalEconomic >= 0,
                        ])>{{ Format::money($totalEconomic, true) }}</td>
                        <td class="px-3"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    {{-- Mobile: card por veículo --}}
    <div class="divide-y divide-slate-100 lg:hidden">
        @forelse ($rows as $row)
            @php
                $net = $row['metrics']['net_result_cents'];
                $economic = $row['economic_result_cents'];
                $hasReserves = $row['reserves']['total_cents'] !== 0;
                $href = route('dre.index', ['from' => $filters['from'], 'to' => $filters['to'], 'vehicle' => $row['vehicle_id']]);
            @endphp
            <a href="{{ $href }}" class="block px-4 py-3 active:bg-slate-50">
                <div class="flex items-center justify-between gap-3">
                    <x-ui.plate-chip :plate="$row['plate']" :type="$row['type']" />
                    <span @class([
                        'font-mono tabular text-sm font-medium',
                        'text-danger-700' => $net < 0,
                        'text-slate-900' => $net >= 0,
                    ])>{{ Format::money($net, true) }}</span>
                </div>
                <div class="mt-2">
                    @if ($row['km'] > 0)
                        <x-ui.km-gauge :revenue="$row['per_km']['revenue']" :cost="$row['per_km']['cost']"
                            :breakeven="$row['per_km']['breakeven']" compact />
                    @else
                        <span class="text-2xs text-slate-400">sem km no período</span>
                    @endif
                </div>
                <div class="mt-2 flex gap-4 text-2xs text-slate-500">
                    <span>{{ $row['km'] > 0 ? Format::km($row['km']) : '—' }}</span>
                    <span>{{ Format::consumption($row['consumption']) }}</span>
                    @if ($hasReserves)
                        <span>Econ.: <span class="font-mono tabular {{ $economic < 0 ? 'text-danger-700' : 'text-slate-700' }}">{{ Format::money($economic, true) }}</span></span>
                    @endif
                </div>
            </a>
        @empty
            <div class="px-4 py-12 text-center">
                <p class="font-display text-lg font-semibold text-slate-900">Nenhum veículo com movimento no período.</p>
                <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">Importe CT-e e lance despesas para ver o resultado por veículo.</p>
            </div>
        @endforelse
    </div>
</div>
