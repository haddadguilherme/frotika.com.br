@php
    /** @var \App\Domain\Fleet\Models\Driver|null $driver */
    $driver = $driver ?? null;
    $categories = \App\Domain\Fleet\Enums\CnhCategory::cases();
    $statuses = \App\Domain\Fleet\Enums\DriverStatus::cases();

    $val = fn (string $field) => old($field, $driver?->getAttribute($field));
    $enumVal = fn (string $field) => old($field, $driver?->getAttribute($field)?->value);
    $cpfValue = old('cpf', $driver?->getAttribute('cpf') ? \App\Support\Cpf\Cpf::format($driver->getAttribute('cpf')) : '');
    $expiresAt = old('cnh_expires_at', $driver?->getAttribute('cnh_expires_at')?->format('Y-m-d'));
@endphp

<div class="space-y-6">
    <section>
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Identificação</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input label="Nome" name="name" :value="$val('name')" maxlength="120" required
                placeholder="Nome completo" />

            <x-ui.input label="CPF" name="cpf" :value="$cpfValue" inputmode="numeric" maxlength="14"
                class="font-mono tabular" placeholder="000.000.000-00" required />

            <x-ui.select label="Situação" name="status" required>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($enumVal('status') ?? 'active') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-ui.select>
        </div>
    </section>

    <section>
        <h2 class="mb-3 text-sm font-semibold text-slate-900">Habilitação (CNH)</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.input label="Número da CNH" name="cnh_number" :value="$val('cnh_number')" inputmode="numeric"
                maxlength="20" class="font-mono tabular" placeholder="Opcional" />

            <x-ui.select label="Categoria" name="cnh_category">
                <option value="">—</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->value }}" @selected($enumVal('cnh_category') === $category->value)>{{ $category->label() }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.input label="Vencimento" name="cnh_expires_at" :value="$expiresAt" type="date" />
        </div>
        <p class="mt-2 text-xs text-slate-500">O sistema destaca motoristas com a CNH vencida ou a vencer em {{ \App\Domain\Fleet\Models\Driver::CNH_ALERT_DAYS }} dias.</p>
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
    (function () {
        const cpf = document.getElementById('cpf');
        if (!cpf) return;
        const mask = (v) => {
            const d = v.replace(/\D/g, '').slice(0, 11);
            return d
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                .replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
        };
        cpf.addEventListener('input', () => { cpf.value = mask(cpf.value); });
        cpf.value = mask(cpf.value);
    })();
</script>
