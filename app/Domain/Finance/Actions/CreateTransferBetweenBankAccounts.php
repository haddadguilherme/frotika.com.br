<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Enums\FinancialEntryPaymentMethod;
use App\Domain\Finance\Enums\FinancialEntryStatus;
use App\Domain\Finance\Enums\FinancialEntryType;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Tenancy\Models\Company;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CreateTransferBetweenBankAccounts
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly RecalculateBankAccountCurrentBalance $recalculateBankAccountCurrentBalance,
    ) {}

    /**
     * @param  array{
     *     origin_bank_account_id: int,
     *     destination_bank_account_id: int,
     *     transfer_date: string,
     *     amount_cents: int,
     *     description?: string|null,
     *     document_number?: string|null,
     *     payment_method?: string|null
     * }  $data
     * @return array{expense_entry_id: int, revenue_entry_id: int}
     */
    public function execute(Company $company, int $createdBy, array $data): array
    {
        $validated = Validator::make($data, [
            'origin_bank_account_id' => ['required', 'integer', 'min:1'],
            'destination_bank_account_id' => ['required', 'integer', 'min:1', 'different:origin_bank_account_id'],
            'transfer_date' => ['required', 'date'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:200'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['nullable', 'string', 'in:'.implode(',', array_map(
                static fn (FinancialEntryPaymentMethod $paymentMethod): string => $paymentMethod->value,
                FinancialEntryPaymentMethod::cases(),
            ))],
        ])->validate();

        return $this->tenant->runFor($company, function () use ($validated, $createdBy, $company): array {
            return DB::transaction(function () use ($validated, $createdBy, $company): array {
                $originAccount = BankAccount::query()->find($validated['origin_bank_account_id']);

                if ($originAccount === null || ! $originAccount->active) {
                    throw ValidationException::withMessages([
                        'origin_bank_account_id' => 'Conta de origem invalida para a empresa ativa.',
                    ]);
                }

                $destinationAccount = BankAccount::query()->find($validated['destination_bank_account_id']);

                if ($destinationAccount === null || ! $destinationAccount->active) {
                    throw ValidationException::withMessages([
                        'destination_bank_account_id' => 'Conta de destino invalida para a empresa ativa.',
                    ]);
                }

                $transferCategory = FinancialCategory::query()
                    ->where('code', '8.4')
                    ->where('active', true)
                    ->first();

                if ($transferCategory === null) {
                    throw ValidationException::withMessages([
                        'financial_category_id' => 'Categoria de transferencia (8.4) nao encontrada para a empresa ativa.',
                    ]);
                }

                $description = trim((string) ($validated['description'] ?? ''));
                if ($description === '') {
                    $description = 'Transferencia entre contas';
                }

                $expenseEntry = FinancialEntry::query()->create([
                    'company_id' => $company->getKey(),
                    'bank_account_id' => $originAccount->getKey(),
                    'financial_category_id' => $transferCategory->getKey(),
                    'type' => FinancialEntryType::Expense->value,
                    'description' => $description,
                    'document_number' => $validated['document_number'] ?? null,
                    'competence_date' => $validated['transfer_date'],
                    'due_date' => null,
                    'paid_at' => $validated['transfer_date'],
                    'amount_cents' => $validated['amount_cents'],
                    'status' => FinancialEntryStatus::Settled->value,
                    'payment_method' => $validated['payment_method'] ?? FinancialEntryPaymentMethod::BankTransfer->value,
                    'sourceable_type' => null,
                    'sourceable_id' => null,
                    'transfer_pair_id' => null,
                    'recurrence_id' => null,
                    'attachment_path' => null,
                    'reconciled_at' => null,
                    'created_by' => $createdBy,
                ]);

                $revenueEntry = FinancialEntry::query()->create([
                    'company_id' => $company->getKey(),
                    'bank_account_id' => $destinationAccount->getKey(),
                    'financial_category_id' => $transferCategory->getKey(),
                    'type' => FinancialEntryType::Revenue->value,
                    'description' => $description,
                    'document_number' => $validated['document_number'] ?? null,
                    'competence_date' => $validated['transfer_date'],
                    'due_date' => null,
                    'paid_at' => $validated['transfer_date'],
                    'amount_cents' => $validated['amount_cents'],
                    'status' => FinancialEntryStatus::Settled->value,
                    'payment_method' => $validated['payment_method'] ?? FinancialEntryPaymentMethod::BankTransfer->value,
                    'sourceable_type' => null,
                    'sourceable_id' => null,
                    'transfer_pair_id' => $expenseEntry->getKey(),
                    'recurrence_id' => null,
                    'attachment_path' => null,
                    'reconciled_at' => null,
                    'created_by' => $createdBy,
                ]);

                $expenseEntry->forceFill([
                    'transfer_pair_id' => $revenueEntry->getKey(),
                ])->save();

                $this->recalculateBankAccountCurrentBalance->execute($company, (int) $originAccount->getKey());
                $this->recalculateBankAccountCurrentBalance->execute($company, (int) $destinationAccount->getKey());

                return [
                    'expense_entry_id' => (int) $expenseEntry->getKey(),
                    'revenue_entry_id' => (int) $revenueEntry->getKey(),
                ];
            });
        });
    }
}
