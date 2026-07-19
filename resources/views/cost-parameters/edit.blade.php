@extends('layouts.app')

@section('title', 'Parâmetros de custo | Frotika')

@php
    use App\Domain\Fleet\Enums\VehicleType;

    $money = fn ($cents) => $cents !== null ? Format::moneyDecimal((int) $cents / 100) : '';
    $num = fn ($value) => $value !== null && $value !== '' ? (string) $value : '';
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
                Reservas e provisões do DRE econômico. O <strong>padrão da empresa</strong> vale para todos os veículos;
                preencha a linha de um veículo só quando ele for diferente — vazio herda o padrão.
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

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="text-2xs font-semibold uppercase tracking-wide text-slate-500">Pneu — jogo (R$)</label>
                    <input type="text" inputmode="decimal" name="default[tire_set_price]"
                        value="{{ $dv('tire_set_price', $money($default?->getAttribute('tire_set_price_cents'))) }}"
                        placeholder="0,00" class="{{ $inputClass }} mt-1" />
                </div>
                <div>
                    <label class="text-2xs font-semibold uppercase tracking-wide text-slate-500">Pneu — vida útil (km)</label>
                    <input type="number" min="0" step="1" name="default[tire_life_km]"
                        value="{{ $dv('tire_life_km', $num($default?->getAttribute('tire_life_km'))) }}"
                        placeholder="0" class="{{ $inputClass }} mt-1" />
                </div>
                <div>
                    <label class="text-2xs font-semibold uppercase tracking-wide text-slate-500">Óleo — troca (R$)</label>
                    <input type="text" inputmode="decimal" name="default[oil_change_cost]"
                        value="{{ $dv('oil_change_cost', $money($default?->getAttribute('oil_change_cost_cents'))) }}"
                        placeholder="0,00" class="{{ $inputClass }} mt-1" />
                </div>
                <div>
                    <label class="text-2xs font-semibold uppercase tracking-wide text-slate-500">Óleo — intervalo (km)</label>
                    <input type="number" min="0" step="1" name="default[oil_interval_km]"
                        value="{{ $dv('oil_interval_km', $num($default?->getAttribute('oil_interval_km'))) }}"
                        placeholder="0" class="{{ $inputClass }} mt-1" />
                </div>
                <div>
                    <label class="text-2xs font-semibold uppercase tracking-wide text-slate-500">Prudencial (% da receita)</label>
                    <input type="text" inputmode="decimal" name="default[prudential_percent]"
                        value="{{ $dv('prudential_percent', $pct($default?->getAttribute('prudential_percent'))) }}"
                        placeholder="0" class="{{ $inputClass }} mt-1" />
                </div>
                <div>
                    <label class="text-2xs font-semibold uppercase tracking-wide text-slate-500">Salário motorista (R$/mês)</label>
                    <input type="text" inputmode="decimal" name="default[driver_salary]"
                        value="{{ $dv('driver_salary', $money($default?->getAttribute('driver_salary_cents'))) }}"
                        placeholder="0,00" class="{{ $inputClass }} mt-1" />
                </div>
                <div>
                    <label class="text-2xs font-semibold uppercase tracking-wide text-slate-500">Pró-labore dono (R$/mês)</label>
                    <input type="text" inputmode="decimal" name="default[owner_prolabore]"
                        value="{{ $dv('owner_prolabore', $money($default?->getAttribute('owner_prolabore_cents'))) }}"
                        placeholder="0,00" class="{{ $inputClass }} mt-1" />
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
                                <th class="{{ $th }}">Pneu jogo (R$)</th>
                                <th class="{{ $th }}">Pneu vida (km)</th>
                                <th class="{{ $th }}">Óleo troca (R$)</th>
                                <th class="{{ $th }}">Óleo interv. (km)</th>
                                <th class="{{ $th }}">Prudencial (%)</th>
                                <th class="{{ $th }}">Salário (R$/mês)</th>
                                <th class="{{ $th }}">Pró-labore (R$/mês)</th>
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
                                        <input type="text" inputmode="decimal" name="vehicles[{{ $id }}][tire_set_price]"
                                            value="{{ $vv($id, 'tire_set_price', $money($o?->getAttribute('tire_set_price_cents'))) }}"
                                            placeholder="{{ $money($default?->getAttribute('tire_set_price_cents')) ?: '0,00' }}"
                                            class="{{ $inputClass }}" />
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <input type="number" min="0" step="1" name="vehicles[{{ $id }}][tire_life_km]"
                                            value="{{ $vv($id, 'tire_life_km', $num($o?->getAttribute('tire_life_km'))) }}"
                                            placeholder="{{ $num($default?->getAttribute('tire_life_km')) ?: '0' }}"
                                            class="{{ $inputClass }}" />
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <input type="text" inputmode="decimal" name="vehicles[{{ $id }}][oil_change_cost]"
                                            value="{{ $vv($id, 'oil_change_cost', $money($o?->getAttribute('oil_change_cost_cents'))) }}"
                                            placeholder="{{ $money($default?->getAttribute('oil_change_cost_cents')) ?: '0,00' }}"
                                            class="{{ $inputClass }}" />
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <input type="number" min="0" step="1" name="vehicles[{{ $id }}][oil_interval_km]"
                                            value="{{ $vv($id, 'oil_interval_km', $num($o?->getAttribute('oil_interval_km'))) }}"
                                            placeholder="{{ $num($default?->getAttribute('oil_interval_km')) ?: '0' }}"
                                            class="{{ $inputClass }}" />
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <input type="text" inputmode="decimal" name="vehicles[{{ $id }}][prudential_percent]"
                                            value="{{ $vv($id, 'prudential_percent', $pct($o?->getAttribute('prudential_percent'))) }}"
                                            placeholder="{{ $pct($default?->getAttribute('prudential_percent')) ?: '0' }}"
                                            class="{{ $inputClass }}" />
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <input type="text" inputmode="decimal" name="vehicles[{{ $id }}][driver_salary]"
                                            value="{{ $vv($id, 'driver_salary', $money($o?->getAttribute('driver_salary_cents'))) }}"
                                            placeholder="{{ $money($default?->getAttribute('driver_salary_cents')) ?: '0,00' }}"
                                            class="{{ $inputClass }}" />
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <input type="text" inputmode="decimal" name="vehicles[{{ $id }}][owner_prolabore]"
                                            value="{{ $vv($id, 'owner_prolabore', $money($o?->getAttribute('owner_prolabore_cents'))) }}"
                                            placeholder="{{ $money($default?->getAttribute('owner_prolabore_cents')) ?: '0,00' }}"
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
