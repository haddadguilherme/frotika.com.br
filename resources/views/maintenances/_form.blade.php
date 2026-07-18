@php
    /** @var \App\Domain\Maintenances\Models\Maintenance|null $maintenance */
    $maintenance = $maintenance ?? null;
    $types = \App\Domain\Maintenances\Enums\MaintenanceType::cases();
    $categories = \App\Domain\Maintenances\Enums\MaintenanceCategory::cases();
    $statuses = \App\Domain\Maintenances\Enums\MaintenanceStatus::cases();

    $workshops = $workshops ?? collect();

    $val = fn (string $field) => old($field, $maintenance?->getAttribute($field));
    $enumVal = fn (string $field) => old($field, $maintenance?->getAttribute($field)?->value);
    $dateVal = fn (string $field) => old($field, $maintenance?->getAttribute($field)?->format('Y-m-d'));

    $moneyVal = function (string $field, string $centsField) use ($maintenance) {
        $cents = $maintenance?->getAttribute($centsField);

        return old($field, $cents !== null ? Format::moneyDecimal((int) $cents / 100) : '');
    };
@endphp

<div class="space-y-6">
    <section>
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Manutenção</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.select label="Veículo" name="vehicle_id" required>
                <option value="">Selecione…</option>
                @foreach ($vehicles as $vehicleOption)
                    <option value="{{ $vehicleOption->getKey() }}" @selected((int) $val('vehicle_id') === (int) $vehicleOption->getKey())>
                        {{ Format::plate($vehicleOption->getAttribute('plate')) }}
                    </option>
                @endforeach
            </x-ui.select>

            <x-ui.select label="Situação" name="status" required>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($enumVal('status') ?? 'open') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.select label="Tipo" name="type" required>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(($enumVal('type') ?? 'corrective') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.select label="Categoria" name="category" required>
                @foreach ($categories as $category)
                    <option value="{{ $category->value }}" @selected(($enumVal('category') ?? 'other') === $category->value)>{{ $category->label() }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.input label="Abertura" name="opened_at" :value="$dateVal('opened_at')" type="date" required />
            <x-ui.input label="Conclusão" name="closed_at" :value="$dateVal('closed_at')" type="date"
                placeholder="Obrigatória quando concluída" />

            <x-ui.input label="Odômetro (km)" name="odometer" :value="$val('odometer')" type="number"
                inputmode="numeric" min="0" class="text-right font-mono tabular" placeholder="Opcional" />

            <x-ui.select label="Oficina cadastrada" name="supplier_id">
                <option value="">— Não vincular</option>
                @foreach ($workshops as $workshopOption)
                    <option value="{{ $workshopOption->getKey() }}" @selected((int) $val('supplier_id') === (int) $workshopOption->getKey())>
                        {{ $workshopOption->getAttribute('trade_name') ?: $workshopOption->getAttribute('legal_name') }}
                    </option>
                @endforeach
            </x-ui.select>

            <x-ui.input label="Oficina (texto livre)" name="workshop_name" :value="$val('workshop_name')" placeholder="Se não estiver cadastrada" />
        </div>
        <p class="mt-2 text-xs text-slate-500">Preventiva entra como custo fixo (4.3); as demais, como manutenção corretiva (3.4). A despesa vira uma conta a pagar no financeiro.</p>
    </section>

    <section>
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Custos</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="labor" class="text-sm font-medium text-slate-700">Mão de obra (R$)</label>
                <input id="labor" name="labor" inputmode="decimal" placeholder="0,00" value="{{ $moneyVal('labor', 'labor_cents') }}"
                    @class([
                        'mt-1.5 block h-11 w-full rounded-md border bg-white px-3 text-right font-mono text-base tabular text-slate-900 placeholder:text-slate-400 focus:ring-2 sm:h-9 sm:text-sm',
                        'border-danger-700 focus:border-danger-700 focus:ring-danger-500/20' => $errors->has('labor_cents'),
                        'border-slate-300 focus:border-brand-500 focus:ring-brand-500/20' => ! $errors->has('labor_cents'),
                    ]) />
                @error('labor_cents')<p class="mt-1 text-sm text-danger-700">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="parts" class="text-sm font-medium text-slate-700">Peças (R$)</label>
                <input id="parts" name="parts" inputmode="decimal" placeholder="0,00" value="{{ $moneyVal('parts', 'parts_cents') }}"
                    @class([
                        'mt-1.5 block h-11 w-full rounded-md border bg-white px-3 text-right font-mono text-base tabular text-slate-900 placeholder:text-slate-400 focus:ring-2 sm:h-9 sm:text-sm',
                        'border-danger-700 focus:border-danger-700 focus:ring-danger-500/20' => $errors->has('parts_cents'),
                        'border-slate-300 focus:border-brand-500 focus:ring-brand-500/20' => ! $errors->has('parts_cents'),
                    ]) />
                @error('parts_cents')<p class="mt-1 text-sm text-danger-700">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="total_display" class="text-sm font-medium text-slate-700">Total (R$)</label>
                <input id="total_display" inputmode="decimal" readonly tabindex="-1"
                    class="mt-1.5 block h-11 w-full rounded-md border border-slate-200 bg-slate-50 px-3 text-right font-mono text-base tabular font-semibold text-slate-900 sm:h-9 sm:text-sm" />
                <p class="mt-1 text-xs text-slate-500">Mão de obra + peças.</p>
            </div>

            <x-ui.input label="Nota fiscal" name="invoice_number" :value="$val('invoice_number')" placeholder="Opcional" class="font-mono tabular" />
        </div>
    </section>

    <section>
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Parada e próxima revisão</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.input label="Horas paradas" name="downtime_hours" :value="$val('downtime_hours')"
                inputmode="decimal" class="text-right font-mono tabular" placeholder="Opcional" />
            <x-ui.input label="Odômetro próx. revisão" name="next_service_odometer" :value="$val('next_service_odometer')"
                type="number" inputmode="numeric" min="0" class="text-right font-mono tabular" placeholder="Opcional" />
            <x-ui.input label="Data próx. revisão" name="next_service_at" :value="$dateVal('next_service_at')" type="date" />
        </div>
    </section>

    <section class="space-y-4">
        <div>
            <label for="description" class="text-sm font-medium text-slate-700">Descrição do serviço</label>
            <textarea id="description" name="description" rows="3"
                class="mt-1.5 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 sm:text-sm">{{ $val('description') }}</textarea>
            @error('description')<p class="mt-1 text-sm text-danger-700">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="notes" class="text-sm font-medium text-slate-700">Observações</label>
            <textarea id="notes" name="notes" rows="2"
                class="mt-1.5 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 sm:text-sm">{{ $val('notes') }}</textarea>
            @error('notes')<p class="mt-1 text-sm text-danger-700">{{ $message }}</p>@enderror
        </div>
    </section>
</div>

<script>
    (function () {
        const brToNumber = (v) => {
            if (!v) return 0;
            const n = v.replace(/[^\d,.-]/g, '').replace(/\./g, '').replace(',', '.');
            const f = parseFloat(n);
            return isNaN(f) ? 0 : f;
        };
        const labor = document.getElementById('labor');
        const parts = document.getElementById('parts');
        const total = document.getElementById('total_display');

        const recalc = () => {
            const t = brToNumber(labor.value) + brToNumber(parts.value);
            total.value = t.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        if (labor && parts && total) {
            labor.addEventListener('input', recalc);
            parts.addEventListener('input', recalc);
            recalc();
        }
    })();
</script>
