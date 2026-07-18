<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Enums\FinancialEntryStatus;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Tenancy\Models\Company;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Dá baixa (liquida) um lançamento previsto: define a conta, a data de pagamento
 * e o meio. Serve tanto para lançamentos manuais quanto para receitas de CT-e —
 * a realização é fato de caixa, não altera o dado de origem.
 */
final class SettleFinancialEntry
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly RecalculateBankAccountCurrentBalance $recalculateBankAccountCurrentBalance,
    ) {}

    /**
     * @param  array{bank_account_id: int, paid_at: string, payment_method?: string|null}  $data
     */
    public function execute(Company $company, int $entryId, array $data): void
    {
        $validated = Validator::make($data, [
            'bank_account_id' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:30'],
        ])->validate();

        $this->tenant->runFor($company, function () use ($validated, $entryId, $company): void {
            $entry = FinancialEntry::query()->find($entryId);

            if ($entry === null) {
                throw ValidationException::withMessages([
                    'entry_id' => 'Lançamento financeiro inválido para a empresa ativa.',
                ]);
            }

            if ($entry->transfer_pair_id !== null) {
                throw ValidationException::withMessages([
                    'entry_id' => 'Transferências já nascem liquidadas e não recebem baixa.',
                ]);
            }

            if ($entry->status === FinancialEntryStatus::Canceled) {
                throw ValidationException::withMessages([
                    'entry_id' => 'Lançamento cancelado não pode receber baixa.',
                ]);
            }

            if ($entry->status === FinancialEntryStatus::Settled) {
                throw ValidationException::withMessages([
                    'entry_id' => 'Lançamento já está liquidado.',
                ]);
            }

            $bankAccount = BankAccount::query()->find($validated['bank_account_id']);

            if ($bankAccount === null || ! $bankAccount->active) {
                throw ValidationException::withMessages([
                    'bank_account_id' => 'Conta bancária inválida para a empresa ativa.',
                ]);
            }

            $entry->forceFill([
                'status' => FinancialEntryStatus::Settled->value,
                'paid_at' => $validated['paid_at'],
                'bank_account_id' => $validated['bank_account_id'],
                'payment_method' => $validated['payment_method'] ?? null,
                'reconciled_at' => null,
            ])->save();

            $this->recalculateBankAccountCurrentBalance->execute($company, (int) $validated['bank_account_id']);
        });
    }
}
