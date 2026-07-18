@php
    /** @var \App\Domain\Finance\Models\FinancialEntry|null $entry */
    $entry = $entry ?? null;

    $catVal = old('financial_category_id', $entry?->getAttribute('financial_category_id'));
    $statusVal = old('status', $entry?->getAttribute('status')?->value ?? 'forecast');
    $vehicleVal = old('vehicle_id', $entry?->getAttribute('vehicle_id'));
    $accountVal = old('bank_account_id', $entry?->getAttribute('bank_account_id'));
    $methodVal = old('payment_method', $entry?->getAttribute('payment_method')?->value);
    $amountVal = old('amount', $entry !== null ? Format::moneyDecimal((int) $entry->getAttribute('amount_cents') / 100) : '');
    $competenceVal = old('competence_date', $entry?->getAttribute('competence_date')?->format('Y-m-d'));
    $dueVal = old('due_date', $entry?->getAttribute('due_date')?->format('Y-m-d'));
    $paidVal = old('paid_at', $entry?->getAttribute('paid_at')?->format('Y-m-d'));

    $revenueCategories = $categories->filter(fn ($c) => $c->type?->value === 'revenue');
    $expenseCategories = $categories->filter(fn ($c) => $c->type?->value === 'expense');
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <x-ui.select label="Categoria" name="financial_category_id" required id="financial_category_id">
            <option value="">Selecione…</option>
            @if ($revenueCategories->isNotEmpty())
                <optgroup label="Receitas">
                    @foreach ($revenueCategories as $category)
                        <option value="{{ $category->getKey() }}" @selected((int) $catVal === (int) $category->getKey())>
                            {{ $category->getAttribute('code') }} — {{ $category->getAttribute('name') }}
                        </option>
                    @endforeach
                </optgroup>
            @endif
            @if ($expenseCategories->isNotEmpty())
                <optgroup label="Despesas">
                    @foreach ($expenseCategories as $category)
                        <option value="{{ $category->getKey() }}" @selected((int) $catVal === (int) $category->getKey())>
                            {{ $category->getAttribute('code') }} — {{ $category->getAttribute('name') }}
                        </option>
                    @endforeach
                </optgroup>
            @endif
        </x-ui.select>
    </div>

    <div class="sm:col-span-2">
        <x-ui.input label="Descrição" name="description" :value="old('description', $entry?->getAttribute('description'))"
            placeholder="Ex.: Frete São Paulo → Curitiba" required maxlength="200" />
    </div>

    <div>
        <label for="amount" class="text-sm font-medium text-slate-700">Valor (R$) <span class="text-danger-700" aria-hidden="true">*</span></label>
        <input id="amount" name="amount" inputmode="decimal" placeholder="0,00" value="{{ $amountVal }}"
            @class([
                'mt-1.5 block h-11 w-full rounded-md border bg-white px-3 text-right font-mono text-base tabular text-slate-900 transition-colors placeholder:text-slate-400 focus:ring-2 sm:h-9 sm:text-sm',
                'border-danger-700 focus:border-danger-700 focus:ring-danger-500/20' => $errors->has('amount_cents'),
                'border-slate-300 focus:border-brand-500 focus:ring-brand-500/20' => ! $errors->has('amount_cents'),
            ]) />
        @error('amount_cents')
            <p class="mt-1 text-sm text-danger-700">{{ $message }}</p>
        @enderror
    </div>

    <x-ui.input label="Documento / NF" name="document_number" :value="old('document_number', $entry?->getAttribute('document_number'))"
        placeholder="Opcional" maxlength="50" />

    <x-ui.input label="Competência (DRE)" name="competence_date" :value="$competenceVal" type="date" required />
    <x-ui.input label="Vencimento" name="due_date" :value="$dueVal" type="date" />

    <div class="sm:col-span-2">
        <x-ui.select label="Veículo (rateio)" name="vehicle_id">
            <option value="">Nenhum / rateio geral</option>
            @foreach ($vehicles as $vehicle)
                <option value="{{ $vehicle->getKey() }}" @selected((int) $vehicleVal === (int) $vehicle->getKey())>{{ Format::plate($vehicle->getAttribute('plate')) }}</option>
            @endforeach
        </x-ui.select>
    </div>

    <div class="sm:col-span-2">
        <span class="text-sm font-medium text-slate-700">Situação</span>
        <div class="mt-1.5 flex gap-2" role="radiogroup">
            <label class="flex-1">
                <input type="radio" name="status" value="forecast" class="peer sr-only" data-status @checked($statusVal === 'forecast') />
                <span class="flex h-9 cursor-pointer items-center justify-center rounded-md border border-slate-300 text-sm text-slate-600 peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:font-medium peer-checked:text-brand-700">Previsto (a receber/pagar)</span>
            </label>
            <label class="flex-1">
                <input type="radio" name="status" value="settled" class="peer sr-only" data-status @checked($statusVal === 'settled') />
                <span class="flex h-9 cursor-pointer items-center justify-center rounded-md border border-slate-300 text-sm text-slate-600 peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:font-medium peer-checked:text-brand-700">Liquidado (pago/recebido)</span>
            </label>
        </div>
    </div>

    <div id="settlement-fields" class="grid gap-4 sm:col-span-2 sm:grid-cols-3 {{ $statusVal === 'settled' ? '' : 'hidden' }}">
        <x-ui.select label="Conta bancária" name="bank_account_id">
            <option value="">Selecione…</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->getKey() }}" @selected((int) $accountVal === (int) $account->getKey())>{{ $account->getAttribute('name') }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.input label="Data do pagamento" name="paid_at" :value="$paidVal" type="date" />
        <x-ui.select label="Meio" name="payment_method">
            <option value="">—</option>
            @foreach ($paymentMethods as $method)
                <option value="{{ $method->value }}" @selected($methodVal === $method->value)>{{ $method->label() }}</option>
            @endforeach
        </x-ui.select>
    </div>
</div>

<script>
    (function () {
        var fields = document.getElementById('settlement-fields');
        document.querySelectorAll('[data-status]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                fields.classList.toggle('hidden', this.value !== 'settled');
            });
        });
    })();
</script>
