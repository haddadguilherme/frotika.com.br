@php
    /** @var \App\Domain\Finance\Models\BankAccount|null $account */
    $account = $account ?? null;
    $types = \App\Domain\Finance\Enums\BankAccountType::cases();

    $val = fn (string $field) => old($field, $account?->getAttribute($field));
    $typeVal = old('type', $account?->getAttribute('type')?->value);
    $balanceVal = old('initial_balance', $account !== null ? Format::moneyDecimal((int) $account->getAttribute('initial_balance_cents') / 100) : '');
    $balanceAt = old('initial_balance_at', $account?->getAttribute('initial_balance_at')?->format('Y-m-d'));
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <x-ui.input label="Nome da conta" name="name" :value="$val('name')" placeholder="Ex.: Banco do Brasil, Caixa, PIX" required />
    </div>

    <x-ui.select label="Tipo" name="type" required>
        @foreach ($types as $type)
            <option value="{{ $type->value }}" @selected(($typeVal ?? 'cash') === $type->value)>{{ $type->label() }}</option>
        @endforeach
    </x-ui.select>

    <x-ui.input label="Banco (código)" name="bank_code" :value="$val('bank_code')" maxlength="10"
        class="font-mono tabular" placeholder="Opcional" />
    <x-ui.input label="Agência" name="agency" :value="$val('agency')" maxlength="20" class="font-mono tabular"
        placeholder="Opcional" />
    <x-ui.input label="Conta" name="number" :value="$val('number')" maxlength="30" class="font-mono tabular"
        placeholder="Opcional" />

    <div>
        <label for="initial_balance" class="text-sm font-medium text-slate-700">Saldo inicial (R$)</label>
        <input id="initial_balance" name="initial_balance" inputmode="decimal" placeholder="0,00"
            value="{{ $balanceVal }}"
            @class([
                'mt-1.5 block h-11 w-full rounded-md border bg-white px-3 text-right font-mono text-base tabular text-slate-900 transition-colors placeholder:text-slate-400 focus:ring-2 sm:h-9 sm:text-sm',
                'border-danger-700 focus:border-danger-700 focus:ring-danger-500/20' => $errors->has('initial_balance_cents'),
                'border-slate-300 focus:border-brand-500 focus:ring-brand-500/20' => ! $errors->has('initial_balance_cents'),
            ]) />
        <p class="mt-1 text-sm text-slate-500">Saldo na data abaixo. O saldo atual é recalculado pelos lançamentos.</p>
        @error('initial_balance_cents')
            <p class="mt-1 text-sm text-danger-700">{{ $message }}</p>
        @enderror
    </div>

    <x-ui.input label="Data do saldo inicial" name="initial_balance_at" :value="$balanceAt" type="date" />

    <div class="sm:col-span-2">
        <label for="notes" class="text-sm font-medium text-slate-700">Observações</label>
        <textarea id="notes" name="notes" rows="2"
            class="mt-1.5 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-base text-slate-900 transition-colors placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 sm:text-sm">{{ $val('notes') }}</textarea>
        @error('notes')
            <p class="mt-1 text-sm text-danger-700">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-2">
        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="hidden" name="is_default" value="0" />
            <input type="checkbox" name="is_default" value="1" @checked((bool) $val('is_default'))
                class="size-4 rounded border-slate-300 text-brand-700 focus:ring-2 focus:ring-brand-500/30" />
            Conta padrão (usada como sugestão nos lançamentos)
        </label>
    </div>
</div>
