<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Enums\FinancialCategoryType;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Finance\Models\Recurrence;
use App\Domain\Tenancy\Models\Company;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UpdateManualFinancialEntry
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly RecalculateBankAccountCurrentBalance $recalculateBankAccountCurrentBalance,
    ) {}

    /**
     * @param  array{
     *     financial_category_id: int,
     *     bank_account_id?: int|null,
     *     type: string,
     *     description: string,
     *     competence_date: string,
     *     due_date?: string|null,
     *     paid_at?: string|null,
     *     amount_cents: int,
     *     status: string,
     *     payment_method?: string|null,
     *     document_number?: string|null,
     *     vehicle_id?: int|null,
     *     driver_id?: int|null,
     *     trip_id?: int|null,
     *     recurrence_id?: int|null,
     *     attachment_path?: string|null
     * }  $data
     */
    public function execute(Company $company, int $entryId, array $data): void
    {
        $validated = Validator::make($data, [
            'financial_category_id' => ['required', 'integer', 'min:1'],
            'bank_account_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['required', 'string', 'in:revenue,expense,transfer'],
            'description' => ['required', 'string', 'max:200'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'competence_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'paid_at' => ['nullable', 'date'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', 'in:forecast,settled'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'vehicle_id' => ['nullable', 'integer', 'min:1'],
            'driver_id' => ['nullable', 'integer', 'min:1'],
            'trip_id' => ['nullable', 'integer', 'min:1'],
            'recurrence_id' => ['nullable', 'integer', 'min:1'],
            'attachment_path' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $this->tenant->runFor($company, function () use ($validated, $entryId, $company): void {
            $entry = FinancialEntry::query()->find($entryId);

            if ($entry === null) {
                throw ValidationException::withMessages([
                    'entry_id' => 'Lancamento financeiro invalido para a empresa ativa.',
                ]);
            }

            if ($entry->sourceable_type !== null || $entry->sourceable_id !== null) {
                throw ValidationException::withMessages([
                    'entry_id' => 'Lancamentos sincronizados devem ser alterados na origem.',
                ]);
            }

            $pairEntry = null;
            $isTransferEntry = $entry->transfer_pair_id !== null;

            if ($isTransferEntry) {
                $pairEntry = FinancialEntry::query()->find((int) $entry->transfer_pair_id);

                if ($pairEntry === null) {
                    throw ValidationException::withMessages([
                        'transfer_pair_id' => 'Par da transferencia nao encontrado para a empresa ativa.',
                    ]);
                }

                if ($pairEntry->sourceable_type !== null || $pairEntry->sourceable_id !== null) {
                    throw ValidationException::withMessages([
                        'transfer_pair_id' => 'Transferencias sincronizadas devem ser alteradas na origem.',
                    ]);
                }

                if ((int) $pairEntry->transfer_pair_id !== (int) $entry->getKey()) {
                    throw ValidationException::withMessages([
                        'transfer_pair_id' => 'Par de transferencia invalido para edicao.',
                    ]);
                }
            }

            $previousBankAccountId = $entry->bank_account_id !== null ? (int) $entry->bank_account_id : null;
            $previousPairBankAccountId = $pairEntry?->bank_account_id !== null ? (int) $pairEntry->bank_account_id : null;

            $category = FinancialCategory::query()->find($validated['financial_category_id']);

            if ($category === null) {
                throw ValidationException::withMessages([
                    'financial_category_id' => 'Categoria financeira invalida para a empresa ativa.',
                ]);
            }

            if (! $category->active || $category->type === null) {
                throw ValidationException::withMessages([
                    'financial_category_id' => 'Categoria financeira precisa ser ativa e analitica.',
                ]);
            }

            /** @var FinancialCategoryType $categoryType */
            $categoryType = $category->type;

            if ($isTransferEntry && $category->code !== '8.4') {
                throw ValidationException::withMessages([
                    'financial_category_id' => 'Transferencias devem usar a categoria 8.4.',
                ]);
            }

            if (! $isTransferEntry && $categoryType->value !== $validated['type']) {
                throw ValidationException::withMessages([
                    'type' => 'Tipo do lancamento deve ser igual ao tipo da categoria.',
                ]);
            }

            if ($isTransferEntry && $entry->type->value !== $validated['type']) {
                throw ValidationException::withMessages([
                    'type' => 'Nao e permitido trocar o tipo de um lancamento de transferencia.',
                ]);
            }

            if ($isTransferEntry && $validated['status'] !== 'settled') {
                throw ValidationException::withMessages([
                    'status' => 'Transferencias entre contas devem permanecer liquidadas.',
                ]);
            }

            if ($validated['status'] === 'forecast' && ($validated['paid_at'] ?? null) !== null) {
                throw ValidationException::withMessages([
                    'paid_at' => 'Lancamento previsto nao pode ter data de pagamento.',
                ]);
            }

            if ($validated['status'] === 'forecast' && ($validated['bank_account_id'] ?? null) !== null) {
                throw ValidationException::withMessages([
                    'bank_account_id' => 'Lancamento previsto nao pode ter conta bancaria vinculada.',
                ]);
            }

            if ($validated['status'] === 'settled' && ($validated['paid_at'] ?? null) === null) {
                throw ValidationException::withMessages([
                    'paid_at' => 'Lancamento liquidado exige data de pagamento.',
                ]);
            }

            if ($validated['status'] === 'settled' && ($validated['bank_account_id'] ?? null) === null) {
                throw ValidationException::withMessages([
                    'bank_account_id' => 'Lancamento liquidado exige conta bancaria.',
                ]);
            }

            if (($validated['bank_account_id'] ?? null) !== null) {
                $bankAccount = BankAccount::query()->find($validated['bank_account_id']);

                if ($bankAccount === null || ! $bankAccount->active) {
                    throw ValidationException::withMessages([
                        'bank_account_id' => 'Conta bancaria invalida para a empresa ativa.',
                    ]);
                }
            }

            if (($validated['recurrence_id'] ?? null) !== null) {
                $recurrence = Recurrence::query()->find($validated['recurrence_id']);

                if ($recurrence === null || ! $recurrence->active) {
                    throw ValidationException::withMessages([
                        'recurrence_id' => 'Recorrencia invalida para a empresa ativa.',
                    ]);
                }

                if ($recurrence->type->value !== $validated['type']) {
                    throw ValidationException::withMessages([
                        'recurrence_id' => 'Recorrencia deve possuir o mesmo tipo do lancamento.',
                    ]);
                }

                if ((int) $recurrence->financial_category_id !== (int) $validated['financial_category_id']) {
                    throw ValidationException::withMessages([
                        'recurrence_id' => 'Recorrencia deve possuir a mesma categoria do lancamento.',
                    ]);
                }
            }

            if ($isTransferEntry && $pairEntry?->bank_account_id !== null && (int) $validated['bank_account_id'] === (int) $pairEntry->bank_account_id) {
                throw ValidationException::withMessages([
                    'bank_account_id' => 'Conta de origem e destino da transferencia devem ser diferentes.',
                ]);
            }

            $entry->forceFill([
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'financial_category_id' => $validated['financial_category_id'],
                'vehicle_id' => $validated['vehicle_id'] ?? null,
                'driver_id' => $validated['driver_id'] ?? null,
                'trip_id' => $validated['trip_id'] ?? null,
                'type' => $validated['type'],
                'description' => $validated['description'],
                'document_number' => $validated['document_number'] ?? null,
                'competence_date' => $validated['competence_date'],
                'due_date' => $validated['due_date'] ?? null,
                'paid_at' => $validated['paid_at'] ?? null,
                'amount_cents' => $validated['amount_cents'],
                'status' => $validated['status'],
                'payment_method' => $validated['payment_method'] ?? null,
                'recurrence_id' => $validated['recurrence_id'] ?? null,
                'attachment_path' => $validated['attachment_path'] ?? null,
                'reconciled_at' => null,
            ])->save();

            if ($isTransferEntry && $pairEntry !== null) {
                $pairEntry->forceFill([
                    'financial_category_id' => $validated['financial_category_id'],
                    'description' => $validated['description'],
                    'document_number' => $validated['document_number'] ?? null,
                    'competence_date' => $validated['competence_date'],
                    'due_date' => $validated['due_date'] ?? null,
                    'paid_at' => $validated['paid_at'] ?? null,
                    'amount_cents' => $validated['amount_cents'],
                    'status' => $validated['status'],
                    'payment_method' => $validated['payment_method'] ?? null,
                    'reconciled_at' => null,
                ])->save();
            }

            $affectedBankAccountIds = array_values(array_unique(array_filter([
                $previousBankAccountId,
                $entry->bank_account_id !== null ? (int) $entry->bank_account_id : null,
                $previousPairBankAccountId,
                $pairEntry?->bank_account_id !== null ? (int) $pairEntry->bank_account_id : null,
            ], static fn ($bankAccountId): bool => $bankAccountId !== null)));

            foreach ($affectedBankAccountIds as $bankAccountId) {
                $this->recalculateBankAccountCurrentBalance->execute($company, $bankAccountId);
            }
        });
    }
}
