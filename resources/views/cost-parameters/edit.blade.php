@extends('layouts.app')

@section('title', 'Parâmetros de custo | Frotika')

@php
    use App\Domain\Fleet\Enums\VehicleType;

    $money = fn ($cents) => $cents !== null ? Format::moneyDecimal((int) $cents / 100) : '';
    $perKm = fn ($value) => $value !== null && $value !== '' ? number_format((float) $value, 4, ',', '') : '';
    $pct = fn ($value) => $value !== null && $value !== '' ? rtrim(rtrim(number_format((float) $value, 2, ',', ''), '0'), ',') : '';

    // Valor a exibir no input: old() tem prioridade; senão o gravado.
    $dv = fn (string $field, $stored) => old("default.$field", $stored);
    $vv = fn (int $id, string $field, $stored) => old("vehicles.$id.$field", $stored);

    $inputClass = 'h-8 w-full rounded-md border border-slate-300 bg-white px-2 text-sm text-right font-mono tabular focus:border-brand-500 focus:outline-none';
    $th = 'px-2 py-2 text-2xs font-semibold uppercase tracking-wide text-slate-500 text-right';
@endphp

@section('content')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-display text-xl font-semibold text-slate-900">Parâmetros de custo</h1>
            <p class="mt-0.5 max-w-2xl text-sm text-slate-500">
                Reservas e provisões do DRE econômico. As reservas são em <strong>R$/km</strong> (aplicadas sobre a distância
                do período), o pró-labore é <strong>% da receita líquida</strong> e o salário é <strong>R$/mês</strong>.
                O <strong>padrão da empresa</strong> vale para todos os veículos; preencha a linha de um veículo só quando
                ele for diferente — vazio herda o padrão.
            </p>
        </div>
        <x-ui.link-button href="{{ route('dre.index') }}" variant="secondary" size="sm">← DRE veicular</x-ui.link-button>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-success-300 bg-success-50 px-3 py-2 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-md border border-danger-300 bg-danger-50 px-3 py-2 text-sm text-danger-700">
            Revise os campos destacados — há valores inválidos.
        </div>
    @endif

    <form method="POST" action="{{ route('cost-parameters.update') }}">
        @csrf
        @method('PUT')

        {{-- Padrão da empresa --}}
        <section class="mb-6 rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="mb-1 text-sm font-semibold text-slate-900">Padrão da empresa</h2>
            <p class="mb-4 text-2xs text-slate-500">Aplicado a todo veículo sem valor próprio.</p>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label class="text-2xs font-semibold uppercase tracking-wide text-slate-500">Reserva óleo (R$/km)</label>
                    <input type="text" inputmode="decimal" name="default[oil_reserve_per_km]"
                        value="{{ $dv('oil_reserve_per_km', $perKm($default?->getAttribute('oil_reserve_per_km'))) }}"
                        placeholder="0,0000" class="{{ $inputClass }} mt-1" />
                </div>
                <div>
                    <label class="text-2xs font-semibold uppercase tracking-wide text-slate-500">Reserva pneus (R$/km)</label>
                    <input type="text" inputmode="decimal" name="default[tire_reserve_per_km]"
                        value="{{ $dv('tire_reserve_per_km', $perKm($default?->getAttribute('tire_reserve_per_km'))) }}"
                        placeholder="0,0000" class="{{ $inputClass }} mt-1" />
                </div>
                <div>
                    <label class="text-2xs font-semibold uppercase tracking-wide text-slate-500">Reserva prudencial (R$/km)</label>
                    <input type="text" inputmode="decimal" name="default[prudential_reserve_per_km]"
                        value="{{ $dv('prudential_reserve_per_km', $perKm($default?->getAttribute('prudential_reserve_per_km'))) }}"
                        placeholder="0,0000" class="{{ $inputClass }} mt-1" />
                </div>
                <div>
                    <label class="text-2xs font-semibold uppercase tracking-wide text-slate-500">Salário motorista (R$/mês)</label>
                    <input type="text" inputmode="decimal" name="default[driver_salary]"
                        value="{{ $dv('driver_salary', $money($default?->getAttribute('driver_salary_cents'))) }}"
                        placeholder="0,00" class="{{ $inputClass }} mt-1" />
                </div>
                <div>
                    <label class="text-2xs font-semibold uppercase tracking-wide text-slate-500">Pró-labore (% da receita)</label>
                    <input type="text" inputmode="decimal" name="default[prolabore_percent]"
                        value="{{ $dv('prolabore_percent', $pct($default?->getAttribute('prolabore_percent'))) }}"
                        placeholder="0" class="{{ $inputClass }} mt-1" />
                </div>
            </div>
        </section>

        {{-- Override por veículo --}}
        <section class="rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-900">Por veículo</h2>
                <p class="text-2xs text-slate-500">Deixe em branco para herdar o padrão da empresa (mostrado como marca-d'água).</p>
            </div>

            @if ($vehicles->isEmpty())
                <p class="px-4 py-8 text-center text-sm text-slate-500">Nenhum veículo cadastrado ainda.</p>
            @else
                <div class="overflow-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200">
                                <th class="px-3 py-2 text-left text-2xs font-semibold uppercase tracking-wide text-slate-500">Placa</th>
                                <th class="{{ $th }}">Óleo (R$/km)</th>
                                <th class="{{ $th }}">Pneus (R$/km)</th>
                                <th class="{{ $th }}">Prudencial (R$/km)</th>
                                <th class="{{ $th }}">Salário (R$/mês)</th>
                                <th class="{{ $th }}">Pró-labore (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vehicles as $vehicleRow)
                                @php
                                    $id = (int) $vehicleRow->getKey();
                                    $o = $overrides->get($id);
                                @endphp
                                <tr class="border-b border-slate-100">
                                    <td class="px-3 py-1.5 whitespace-nowrap">
                                        <x-ui.plate-chip :plate="$vehicleRow->getAttribute('plate')" :type="$vehicleRow->type?->value" />
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <input type="text" inputmode="decimal" name="vehicles[{{ $id }}][oil_reserve_per_km]"
                                            value="{{ $vv($id, 'oil_reserve_per_km', $perKm($o?->getAttribute('oil_reserve_per_km'))) }}"
                                            placeholder="{{ $perKm($default?->getAttribute('oil_reserve_per_km')) ?: '0,0000' }}"
                                            class="{{ $inputClass }}" />
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <input type="text" inputmode="decimal" name="vehicles[{{ $id }}][tire_reserve_per_km]"
                                            value="{{ $vv($id, 'tire_reserve_per_km', $perKm($o?->getAttribute('tire_reserve_per_km'))) }}"
                                            placeholder="{{ $perKm($default?->getAttribute('tire_reserve_per_km')) ?: '0,0000' }}"
                                            class="{{ $inputClass }}" />
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <input type="text" inputmode="decimal" name="vehicles[{{ $id }}][prudential_reserve_per_km]"
                                            value="{{ $vv($id, 'prudential_reserve_per_km', $perKm($o?->getAttribute('prudential_reserve_per_km'))) }}"
                                            placeholder="{{ $perKm($default?->getAttribute('prudential_reserve_per_km')) ?: '0,0000' }}"
                                            class="{{ $inputClass }}" />
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <input type="text" inputmode="decimal" name="vehicles[{{ $id }}][driver_salary]"
                                            value="{{ $vv($id, 'driver_salary', $money($o?->getAttribute('driver_salary_cents'))) }}"
                                            placeholder="{{ $money($default?->getAttribute('driver_salary_cents')) ?: '0,00' }}"
                                            class="{{ $inputClass }}" />
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <input type="text" inputmode="decimal" name="vehicles[{{ $id }}][prolabore_percent]"
                                            value="{{ $vv($id, 'prolabore_percent', $pct($o?->getAttribute('prolabore_percent'))) }}"
                                            placeholder="{{ $pct($default?->getAttribute('prolabore_percent')) ?: '0' }}"
                                            class="{{ $inputClass }}" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3">
                <x-ui.button type="submit">Salvar parâmetros</x-ui.button>
            </div>
        </section>
    </form>
@endsection
