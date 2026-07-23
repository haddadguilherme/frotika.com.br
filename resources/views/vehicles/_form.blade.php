@php
    /** @var \App\Domain\Fleet\Models\Vehicle|null $vehicle */
    $vehicle = $vehicle ?? null;
    $types = \App\Domain\Fleet\Enums\VehicleType::cases();
    $statuses = \App\Domain\Fleet\Enums\VehicleStatus::cases();
    $ownerships = \App\Domain\Fleet\Enums\VehicleOwnership::cases();
    $financingTypes = \App\Domain\Fleet\Enums\VehicleFinancingType::cases();
    $bodyTypes = \App\Domain\Fleet\Enums\VehicleBodyType::cases();
    $fuelTypes = \App\Domain\Fleet\Enums\VehicleFuelType::cases();

    $val = fn(string $field) => old($field, $vehicle?->getAttribute($field));
    $enumVal = fn(string $field) => old($field, $vehicle?->getAttribute($field)?->value);

    $moneyVal = function (string $field, string $centsField) use ($vehicle) {
        $cents = $vehicle?->getAttribute($centsField);

        return old($field, $cents !== null ? Format::moneyDecimal((int) $cents / 100) : '');
    };

    $acquisitionDate = old('acquisition_date', $vehicle?->getAttribute('acquisition_date')?->format('Y-m-d'));
    $crlvDueAt = old('crlv_due_at', $vehicle?->getAttribute('crlv_due_at')?->format('Y-m-d'));
    $insuranceDueAt = old('insurance_due_at', $vehicle?->getAttribute('insurance_due_at')?->format('Y-m-d'));
    $anttDueAt = old('antt_due_at', $vehicle?->getAttribute('antt_due_at')?->format('Y-m-d'));
    $selectedType = (string) ($enumVal('type') ?? 'tractor');
    $showBodyAndVolume = $selectedType !== \App\Domain\Fleet\Enums\VehicleType::Tractor->value;
    $isProvisioned = (bool) ($vehicle?->getAttribute('provisioned') ?? false);
    $isFinanced = (bool) old('is_financed', $vehicle?->getAttribute('is_financed'));
@endphp

<div class="space-y-6">
    <section>
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Identificação</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-ui.input label="Placa" name="plate" :value="$val('plate')" maxlength="8" inputmode="text" placeholder="ABC1D23"
                    class="font-mono uppercase tabular" required />
                @if ($isProvisioned)
                    <p class="mt-1 text-2xs text-warning-700">Placa importada de CT-e. Confira.</p>
                @endif
            </div>

            <div @class([
                'rounded-md border p-2',
                'border-warning-300 bg-warning-50/60' => $isProvisioned,
                'border-transparent' => ! $isProvisioned,
            ])>
                <x-ui.select :label="$isProvisioned ? 'Tipo (confirmar)' : 'Tipo'" name="type" required>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(($enumVal('type') ?? 'tractor') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </x-ui.select>
                @if ($isProvisioned)
                    <p class="mt-1 text-2xs text-warning-700">O tipo veio de palpite da importação. Revise antes de salvar.</p>
                @endif
            </div>

            <x-ui.select label="Situação" name="status" required>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($enumVal('status') ?? 'active') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-ui.select>
        </div>
    </section>

    <section>
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Especificações</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input label="Marca" name="brand" :value="$val('brand')" placeholder="Opcional" />
            <x-ui.input label="Modelo" name="model" :value="$val('model')" placeholder="Opcional" />

            <x-ui.input label="Ano de fabricação" name="year_manufacture" :value="$val('year_manufacture')" type="number"
                inputmode="numeric" min="1950" max="{{ (int) date('Y') + 1 }}" class="text-right font-mono tabular"
                placeholder="Opcional" />
            <x-ui.input label="Ano do modelo" name="year_model" :value="$val('year_model')" type="number" inputmode="numeric"
                min="1950" max="{{ (int) date('Y') + 1 }}" class="text-right font-mono tabular"
                placeholder="Opcional" />

            <x-ui.input label="RENAVAM" name="renavam" :value="$val('renavam')" inputmode="numeric" maxlength="20"
                class="font-mono tabular" placeholder="Opcional" />
            <x-ui.input label="Chassi" name="chassis" :value="$val('chassis')" maxlength="30" class="font-mono uppercase"
                placeholder="Opcional" />

            <x-ui.input label="RNTRC" name="rntrc" :value="$val('rntrc')" inputmode="numeric" maxlength="12"
                class="font-mono tabular" placeholder="Opcional" />
            <x-ui.input label="Eixos" name="axles" :value="$val('axles')" type="number" inputmode="numeric"
                min="1" max="12" class="text-right font-mono tabular" placeholder="Opcional" />

            <x-ui.input label="Número do motor" name="engine_number" :value="$val('engine_number')" maxlength="60"
                class="font-mono uppercase" placeholder="Opcional" />
            <x-ui.input label="Distância entre eixos (m)" name="axle_distance_m" :value="$val('axle_distance_m')" type="number"
                inputmode="decimal" step="0.01" min="0" max="99.99" class="text-right font-mono tabular"
                placeholder="Opcional" />

            <x-ui.input label="Quantidade de pneus" name="tire_count" :value="$val('tire_count')" type="number"
                inputmode="numeric" min="1" max="24" class="text-right font-mono tabular" placeholder="Opcional" />
            <x-ui.input label="Medida dos pneus" name="tire_size" :value="$val('tire_size')" maxlength="20"
                class="font-mono uppercase" placeholder="Opcional" />

            <div data-body-volume-field @class(['hidden' => ! $showBodyAndVolume])>
                <x-ui.select label="Carroceria" name="body_type">
                    <option value="">—</option>
                    @foreach ($bodyTypes as $bodyType)
                        <option value="{{ $bodyType->value }}" @selected($enumVal('body_type') === $bodyType->value)>{{ $bodyType->label() }}
                        </option>
                    @endforeach
                </x-ui.select>
            </div>

            <x-ui.select label="Combustível" name="fuel_type">
                <option value="">—</option>
                @foreach ($fuelTypes as $fuelType)
                    <option value="{{ $fuelType->value }}" @selected($enumVal('fuel_type') === $fuelType->value)>{{ $fuelType->label() }}
                    </option>
                @endforeach
            </x-ui.select>

            <x-ui.input label="Tara (kg)" name="tare_kg" :value="$val('tare_kg')" type="number" inputmode="numeric"
                min="0" class="text-right font-mono tabular" placeholder="Opcional" />
            <x-ui.input label="Capacidade (kg)" name="capacity_kg" :value="$val('capacity_kg')" type="number" inputmode="numeric"
                min="0" class="text-right font-mono tabular" placeholder="Opcional" />

            <div data-body-volume-field @class(['hidden' => ! $showBodyAndVolume])>
                <x-ui.input label="Capacidade (m³)" name="capacity_m3" :value="$val('capacity_m3')" type="number" inputmode="decimal"
                    step="0.001" min="0" class="text-right font-mono tabular" placeholder="Opcional" />
            </div>
            <x-ui.input label="Tanque (litros)" name="tank_capacity_l" :value="$val('tank_capacity_l')" type="number"
                inputmode="numeric" min="0" class="text-right font-mono tabular" placeholder="Opcional" />
        </div>
    </section>

    <section>
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Propriedade</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.select label="Propriedade" name="ownership" required>
                @foreach ($ownerships as $ownership)
                    <option value="{{ $ownership->value }}" @selected(($enumVal('ownership') ?? 'own') === $ownership->value)>{{ $ownership->label() }}
                    </option>
                @endforeach
            </x-ui.select>

            <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2.5">
                <label for="is_financed" class="flex items-center justify-between gap-3 text-sm font-medium text-slate-700">
                    <span>Veículo financiado</span>
                    <input id="is_financed" type="checkbox" name="is_financed" value="1" @checked($isFinanced)
                        class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500/30" />
                </label>
                <p class="mt-1 text-2xs text-slate-500">Ao marcar, informe o tipo de financiamento e o credor.</p>
            </div>

            <div data-financing-field @class(['sm:col-span-2', 'hidden' => ! $isFinanced])>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select label="Tipo de financiamento" name="financing_type">
                        <option value="">—</option>
                        @foreach ($financingTypes as $financingType)
                            <option value="{{ $financingType->value }}" @selected($enumVal('financing_type') === $financingType->value)>{{ $financingType->label() }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input label="Credor" name="creditor_name" :value="$val('creditor_name')" maxlength="120"
                        placeholder="Opcional" />
                </div>
            </div>
        </div>
    </section>

    <section>
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Documentação e vencimentos</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.input label="Vencimento do CRLV" name="crlv_due_at" :value="$crlvDueAt" type="date" />
            <x-ui.input label="Vencimento do seguro" name="insurance_due_at" :value="$insuranceDueAt" type="date" />
            <x-ui.input label="Vencimento da ANTT" name="antt_due_at" :value="$anttDueAt" type="date" />
        </div>
    </section>

    <section>
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Hodômetro e aquisição</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input label="Hodômetro inicial (km)" name="odometer_initial" :value="$val('odometer_initial') ?? 0" type="number"
                inputmode="numeric" min="0" class="text-right font-mono tabular" />
            <x-ui.input label="Data de aquisição" name="acquisition_date" :value="$acquisitionDate" type="date" />

            <div>
                <label for="acquisition_value" class="text-sm font-medium text-slate-700">Valor de aquisição
                    (R$)</label>
                <input id="acquisition_value" name="acquisition_value" inputmode="decimal" placeholder="0,00"
                    value="{{ $moneyVal('acquisition_value', 'acquisition_value_cents') }}"
                    @class([
                        'mt-1.5 block h-11 w-full rounded-md border bg-white px-3 text-right font-mono text-base tabular text-slate-900 transition-colors placeholder:text-slate-400 focus:ring-2 sm:h-9 sm:text-sm',
                        'border-danger-700 focus:border-danger-700 focus:ring-danger-500/20' => $errors->has(
                            'acquisition_value_cents'),
                        'border-slate-300 focus:border-brand-500 focus:ring-brand-500/20' => !$errors->has(
                            'acquisition_value_cents'),
                    ]) />
                @error('acquisition_value_cents')
                    <p class="mt-1 text-sm text-danger-700">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </section>

    <section>
        <label for="notes" class="text-sm font-medium text-slate-700">Observações</label>
        <textarea id="notes" name="notes" rows="3"
            class="mt-1.5 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base text-slate-900 transition-colors placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 sm:text-sm">{{ $val('notes') }}</textarea>
        @error('notes')
            <p class="mt-1 text-sm text-danger-700">{{ $message }}</p>
        @enderror
    </section>
</div>

<script>
    (function() {
        const plate = document.getElementById('plate');
        if (plate) {
            plate.addEventListener('input', () => {
                plate.value = plate.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);
            });
        }

        const type = document.getElementById('type');
        const bodyVolumeFields = document.querySelectorAll('[data-body-volume-field]');
        const financed = document.getElementById('is_financed');
        const financingFields = document.querySelectorAll('[data-financing-field]');

        const toggleBodyVolumeFields = () => {
            if (!type) {
                return;
            }

            const show = type.value !== '{{ \App\Domain\Fleet\Enums\VehicleType::Tractor->value }}';

            bodyVolumeFields.forEach((field) => {
                field.classList.toggle('hidden', !show);
            });
        };

        if (type) {
            type.addEventListener('change', toggleBodyVolumeFields);
            toggleBodyVolumeFields();
        }

        const toggleFinancingFields = () => {
            if (!financed) {
                return;
            }

            financingFields.forEach((field) => {
                field.classList.toggle('hidden', !financed.checked);
            });
        };

        if (financed) {
            financed.addEventListener('change', toggleFinancingFields);
            toggleFinancingFields();
        }
    })();
</script>
