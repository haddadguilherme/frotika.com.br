@extends('layouts.app')

@section('title', 'Lançamento | Frotika')

@php
    use App\Domain\Finance\Enums\FinancialEntryStatus;
    use App\Domain\Finance\Enums\FinancialEntryType;

    $status = $entry->getAttribute('status');
    $type = $entry->getAttribute('type');
    $statusChip = match ($status) {
        FinancialEntryStatus::Settled => 'border-success-300 bg-success-50 text-success-700',
        FinancialEntryStatus::Forecast => 'border-warning-300 bg-warning-50 text-warning-700',
        default => 'border-slate-300 bg-slate-50 text-slate-500',
    };
    $amountCents = (int) $entry->getAttribute('amount_cents');
    $signedCents = $type === FinancialEntryType::Expense ? -$amountCents : $amountCents;
@endphp

@section('content')
    <div class="mx-auto max-w-3xl">
        <x-ui.page-header title="Lançamento" :subtitle="$entry->getAttribute('description')">
            <x-slot:actions>
                <x-ui.link-button href="{{ route('financial-entries.index') }}" variant="secondary">Voltar</x-ui.link-button>
                @if ($canManage && ! $isSynced && $entry->getAttribute('transfer_pair_id') === null && $status !== FinancialEntryStatus::Canceled)
                    <x-ui.link-button href="{{ route('financial-entries.edit', ['entry' => $entry->getKey()]) }}" variant="secondary">Editar</x-ui.link-button>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('warning'))
            <div class="mb-4 rounded-md border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-700">{{ session('warning') }}</div>
        @endif

        <x-ui.card class="border-slate-200 bg-white">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-2xs font-semibold {{ $statusChip }}">{{ $status->label() }}</span>
                        <span class="text-xs uppercase tracking-wide text-slate-400">{{ $type->label() }}</span>
                        @if ($isSynced)
                            <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-50 px-2 py-0.5 text-2xs font-semibold text-slate-500">Sincronizado</span>
                        @endif
                    </div>
                    <p class="mt-2 font-display text-lg font-semibold text-slate-900">{{ $entry->getAttribute('description') }}</p>
                </div>
                <div @class([
                    'text-right font-mono text-2xl font-semibold tabular',
                    'text-danger-700' => $signedCents < 0,
                    'text-slate-900' => $signedCents >= 0,
                ])>{{ Format::money($signedCents) }}</div>
            </div>

            <dl class="mt-5 grid gap-x-6 gap-y-3 border-t border-slate-200 pt-4 text-sm sm:grid-cols-2">
                <div class="flex justify-between sm:block">
                    <dt class="text-slate-500">Categoria</dt>
                    <dd class="text-slate-900">{{ $entry->category?->getAttribute('code') }} — {{ $entry->category?->getAttribute('name') }}</dd>
                </div>
                <div class="flex justify-between sm:block">
                    <dt class="text-slate-500">Documento</dt>
                    <dd class="font-mono tabular text-slate-900">{{ $entry->getAttribute('document_number') ?: '—' }}</dd>
                </div>
                <div class="flex justify-between sm:block">
                    <dt class="text-slate-500">Competência (DRE)</dt>
                    <dd class="font-mono tabular text-slate-900">{{ Format::date($entry->getAttribute('competence_date')) }}</dd>
                </div>
                <div class="flex justify-between sm:block">
                    <dt class="text-slate-500">Vencimento</dt>
                    <dd class="font-mono tabular text-slate-900">{{ Format::date($entry->getAttribute('due_date')) ?: '—' }}</dd>
                </div>
                <div class="flex justify-between sm:block">
                    <dt class="text-slate-500">Pagamento</dt>
                    <dd class="font-mono tabular text-slate-900">{{ Format::date($entry->getAttribute('paid_at')) ?: '—' }}</dd>
                </div>
                <div class="flex justify-between sm:block">
                    <dt class="text-slate-500">Conta</dt>
                    <dd class="text-slate-900">{{ $entry->bankAccount?->getAttribute('name') ?: '—' }}</dd>
                </div>
                @if ($vehicle !== null)
                    <div class="flex justify-between sm:block">
                        <dt class="text-slate-500">Veículo</dt>
                        <dd><a href="{{ route('vehicles.show', ['vehicle' => $vehicle->getKey()]) }}" class="font-mono tabular text-brand-700 hover:text-brand-800">{{ Format::plate($vehicle->getAttribute('plate')) }}</a></dd>
                    </div>
                @endif
                @if ($entry->getAttribute('payment_method') !== null)
                    <div class="flex justify-between sm:block">
                        <dt class="text-slate-500">Meio</dt>
                        <dd class="text-slate-900">{{ $entry->getAttribute('payment_method')->label() }}</dd>
                    </div>
                @endif
            </dl>
        </x-ui.card>

        @if ($canManage && $status === FinancialEntryStatus::Forecast)
            <x-ui.card class="mt-4 border-slate-200 bg-white">
                <h2 class="font-display text-base font-semibold text-slate-900">Dar baixa</h2>
                <p class="mt-0.5 text-sm text-slate-500">Marque como {{ $type === FinancialEntryType::Revenue ? 'recebido' : 'pago' }} informando a conta e a data.</p>

                <form method="POST" action="{{ route('financial-entries.settle', ['entry' => $entry->getKey()]) }}" class="mt-4 grid gap-4 sm:grid-cols-3">
                    @csrf
                    <x-ui.select label="Conta bancária" name="bank_account_id" required>
                        <option value="">Selecione…</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->getKey() }}" @selected((int) old('bank_account_id') === (int) $account->getKey())>{{ $account->getAttribute('name') }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input label="Data do pagamento" name="paid_at" type="date" :value="old('paid_at', now()->toDateString())" required />
                    <div class="flex items-end">
                        <x-ui.button type="submit" class="w-full">Confirmar baixa</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        @endif

        @if ($canManage && ! $isSynced && $status !== FinancialEntryStatus::Canceled)
            <form method="POST" action="{{ route('financial-entries.destroy', ['entry' => $entry->getKey()]) }}" class="mt-4"
                onsubmit="return confirm('Cancelar este lançamento?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm text-danger-700 hover:text-danger-800">Cancelar lançamento</button>
            </form>
        @endif
    </div>
@endsection
