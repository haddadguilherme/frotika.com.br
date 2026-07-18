@php
    /** @var \App\Domain\Fuelings\Models\Fueling|null $fueling */
    $fueling = $fueling ?? null;
    $products = \App\Domain\Fuelings\Enums\FuelProduct::cases();
    $tanks = \App\Domain\Fuelings\Enums\FuelTank::cases();
    $paymentMethods = \App\Domain\Fuelings\Enums\FuelingPaymentMethod::cases();

    $drivers = $drivers ?? collect();
    $stations = $stations ?? collect();

    $val = fn (string $field) => old($field, $fueling?->getAttribute($field));
    $enumVal = fn (string $field) => old($field, $fueling?->getAttribute($field)?->value);

    $fueledAt = old('fueled_at', $fueling?->getAttribute('fueled_at')?->format('Y-m-d\TH:i'));

    $litersVal = old('liters', $fueling !== null ? Format::moneyDecimal((float) $fueling->getAttribute('liters'), 3) : '');
    $priceVal = old('price_per_liter', $fueling?->getAttribute('price_per_liter') !== null
        ? Format::moneyDecimal((float) $fueling->getAttribute('price_per_liter'), 3)
        : '');
    $totalVal = old('total', $fueling?->getAttribute('total_cents') !== null
        ? Format::moneyDecimal((int) $fueling->getAttribute('total_cents') / 100)
        : '');
@endphp

<div class="space-y-6">
    <section>
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Abastecimento</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.select label="Veículo" name="vehicle_id" required>
                <option value="">Selecione…</option>
                @foreach ($vehicles as $vehicleOption)
                    <option value="{{ $vehicleOption->getKey() }}" @selected((int) $val('vehicle_id') === (int) $vehicleOption->getKey())>
                        {{ Format::plate($vehicleOption->getAttribute('plate')) }}
                    </option>
                @endforeach
            </x-ui.select>

            <x-ui.input label="Data e hora" name="fueled_at" :value="$fueledAt" type="datetime-local" required />

            <x-ui.select label="Produto" name="product" required>
                @foreach ($products as $product)
                    <option value="{{ $product->value }}" @selected(($enumVal('product') ?? 'diesel_s10') === $product->value)>{{ $product->label() }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.select label="Tanque" name="tank" required>
                @foreach ($tanks as $tank)
                    <option value="{{ $tank->value }}" @selected(($enumVal('tank') ?? 'main') === $tank->value)>{{ $tank->label() }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.input label="Odômetro (km)" name="odometer" :value="$val('odometer')" type="number"
                inputmode="numeric" min="0" class="text-right font-mono tabular" required />

            <x-ui.select label="Motorista" name="driver_id">
                <option value="">— Não informado</option>
                @foreach ($drivers as $driverOption)
                    <option value="{{ $driverOption->getKey() }}" @selected((int) $val('driver_id') === (int) $driverOption->getKey())>
                        {{ $driverOption->getAttribute('name') }}
                    </option>
                @endforeach
            </x-ui.select>

            <label class="flex items-center gap-2 pt-6">
                <input type="checkbox" name="full_tank" value="1" @checked((bool) old('full_tank', $fueling?->getAttribute('full_tank')))
                    class="size-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500/30" />
                <span class="text-sm font-medium text-slate-700">Tanque cheio</span>
            </label>
        </div>
        <p class="mt-2 text-xs text-slate-500">Só entre dois tanques cheios o consumo (km/l) é calculado. Arla 32 e óleo nunca entram no cálculo.</p>
    </section>

    <section>
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Valores</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="liters" class="text-sm font-medium text-slate-700">Litros <span class="text-danger-700" aria-hidden="true">*</span></label>
                <input id="liters" name="liters" inputmode="decimal" placeholder="0,000" value="{{ $litersVal }}"
                    @class([
                        'mt-1.5 block h-11 w-full rounded-md border bg-white px-3 text-right font-mono text-base tabular text-slate-900 placeholder:text-slate-400 focus:ring-2 sm:h-9 sm:text-sm',
                        'border-danger-700 focus:border-danger-700 focus:ring-danger-500/20' => $errors->has('liters'),
                        'border-slate-300 focus:border-brand-500 focus:ring-brand-500/20' => ! $errors->has('liters'),
                    ]) />
                @error('liters')<p class="mt-1 text-sm text-danger-700">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="price_per_liter" class="text-sm font-medium text-slate-700">Preço por litro (R$)</label>
                <input id="price_per_liter" name="price_per_liter" inputmode="decimal" placeholder="0,000" value="{{ $priceVal }}"
                    @class([
                        'mt-1.5 block h-11 w-full rounded-md border bg-white px-3 text-right font-mono text-base tabular text-slate-900 placeholder:text-slate-400 focus:ring-2 sm:h-9 sm:text-sm',
                        'border-danger-700 focus:border-danger-700 focus:ring-danger-500/20' => $errors->has('price_per_liter'),
                        'border-slate-300 focus:border-brand-500 focus:ring-brand-500/20' => ! $errors->has('price_per_liter'),
                    ]) />
                <p class="mt-1 text-xs text-slate-500">Opcional — deriva do total.</p>
                @error('price_per_liter')<p class="mt-1 text-sm text-danger-700">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="total" class="text-sm font-medium text-slate-700">Total (R$) <span class="text-danger-700" aria-hidden="true">*</span></label>
                <input id="total" name="total" inputmode="decimal" placeholder="0,00" value="{{ $totalVal }}"
                    @class([
                        'mt-1.5 block h-11 w-full rounded-md border bg-white px-3 text-right font-mono text-base tabular text-slate-900 placeholder:text-slate-400 focus:ring-2 sm:h-9 sm:text-sm',
                        'border-danger-700 focus:border-danger-700 focus:ring-danger-500/20' => $errors->has('total_cents'),
                        'border-slate-300 focus:border-brand-500 focus:ring-brand-500/20' => ! $errors->has('total_cents'),
                    ]) />
                @error('total_cents')<p class="mt-1 text-sm text-danger-700">{{ $message }}</p>@enderror
            </div>

            <x-ui.select label="Pagamento" name="payment_method" required>
                @foreach ($paymentMethods as $method)
                    <option value="{{ $method->value }}" @selected(($enumVal('payment_method') ?? 'cash') === $method->value)>{{ $method->label() }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.input label="Nota/cupom" name="invoice_number" :value="$val('invoice_number')" placeholder="Opcional" class="font-mono tabular" />
        </div>
        <p class="mt-2 text-xs text-slate-500">À vista (dinheiro, pix, débito, cartão de abastecimento) baixa na conta padrão. Crédito e faturado viram conta a pagar.</p>
    </section>

    <section>
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Posto</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.select label="Posto cadastrado" name="supplier_id" class="sm:col-span-3">
                <option value="">— Não vincular</option>
                @foreach ($stations as $stationOption)
                    <option value="{{ $stationOption->getKey() }}" @selected((int) $val('supplier_id') === (int) $stationOption->getKey())>
                        {{ $stationOption->getAttribute('trade_name') ?: $stationOption->getAttribute('legal_name') }}
                    </option>
                @endforeach
            </x-ui.select>
        </div>
        <p class="mt-2 text-xs text-slate-500">Vincule um posto do cadastro de parceiros (tipo Posto) ou preencha os campos abaixo à mão.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <x-ui.input label="Nome" name="station_name" :value="$val('station_name')" placeholder="Opcional" class="sm:col-span-2" />
            <x-ui.input label="Cidade" name="station_city" :value="$val('station_city')" placeholder="Opcional" />
            <x-ui.input label="UF" name="station_state" :value="$val('station_state')" maxlength="2"
                class="uppercase" placeholder="Ex.: SP" />
        </div>
    </section>

    <section>
        <label for="notes" class="text-sm font-medium text-slate-700">Observações</label>
        <textarea id="notes" name="notes" rows="3"
            class="mt-1.5 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 sm:text-sm">{{ $val('notes') }}</textarea>
        @error('notes')<p class="mt-1 text-sm text-danger-700">{{ $message }}</p>@enderror

        <label class="mt-3 flex items-start gap-2">
            <input type="checkbox" name="allow_odometer_rollback" value="1" @checked((bool) old('allow_odometer_rollback'))
                class="mt-0.5 size-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500/30" />
            <span class="text-sm text-slate-600">Confirmar odômetro menor que o último conhecido (troca de painel / correção).</span>
        </label>
    </section>
</div>

<script>
    (function () {
        const brToNumber = (v) => {
            if (!v) return null;
            const n = v.replace(/[^\d,.-]/g, '').replace(/\./g, '').replace(',', '.');
            const f = parseFloat(n);
            return isNaN(f) ? null : f;
        };
        const liters = document.getElementById('liters');
        const price = document.getElementById('price_per_liter');
        const total = document.getElementById('total');

        const recalcTotal = () => {
            const l = brToNumber(liters.value);
            const p = brToNumber(price.value);
            if (l !== null && p !== null && !total.dataset.touched) {
                total.value = (l * p).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        };

        if (liters && price && total) {
            total.addEventListener('input', () => { total.dataset.touched = '1'; });
            liters.addEventListener('input', recalcTotal);
            price.addEventListener('input', recalcTotal);
        }
    })();
</script>
