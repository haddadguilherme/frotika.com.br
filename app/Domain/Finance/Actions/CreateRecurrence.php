<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Enums\FinancialEntryPaymentMethod;
use App\Domain\Finance\Enums\FinancialEntryType;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Finance\Models\Recurrence;
use App\Domain\Tenancy\Models\Company;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CreateRecurrence
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @param  array{
     *     financial_category_id: int,
     *     type: string,
     *     description: string,
     *     amount_cents: int,
     *     frequency: string,
     *     starts_at: string,
     *     day_of_month?: int|null,
     *     document_number?: string|null,
     *     ends_at?: string|null,
     *     installments?: int|null,
     *     vehicle_id?: int|null,
     *     driver_id?: int|null,
     *     trip_id?: int|null,
     *     payment_method?: string|null,
     *     active?: bool
     * }  $data
     */
    public function execute(Company $company, int $createdBy, array $data): int
    {
        $paymentMethods = array_map(
            static fn (FinancialEntryPaymentMethod $paymentMethod): string => $paymentMethod->value,
            FinancialEntryPaymentMethod::cases(),
        );

        $validated = Validator::make($data, [
            'financial_category_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', Rule::in([FinancialEntryType::Revenue->value, FinancialEntryType::Expense->value])],
            'description' => ['required', 'string', 'max:200'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'frequency' => ['required', 'string', Rule::in(['monthly', 'weekly', 'yearly'])],
            'starts_at' => ['required', 'date'],
            'day_of_month' => ['nullable', 'integer', 'between:1,31'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'installments' => ['nullable', 'integer', 'min:1'],
            'vehicle_id' => ['nullable', 'integer', 'min:1'],
            'driver_id' => ['nullable', 'integer', 'min:1'],
            'trip_id' => ['nullable', 'integer', 'min:1'],
            'payment_method' => ['nullable', 'string', Rule::in($paymentMethods)],
            'active' => ['sometimes', 'boolean'],
        ])->validate();

        if (in_array($validated['frequency'], ['monthly', 'yearly'], true) && ($validated['day_of_month'] ?? null) === null) {
            throw ValidationException::withMessages([
                'day_of_month' => 'Recorrencias mensais e anuais exigem dia do mes.',
            ]);
        }

        if ($validated['frequency'] === 'weekly' && ($validated['day_of_month'] ?? null) !== null) {
            throw ValidationException::withMessages([
                'day_of_month' => 'Recorrencia semanal nao usa dia do mes.',
            ]);
        }

        return $this->tenant->runFor($company, function () use ($validated, $createdBy, $company): int {
            $category = FinancialCategory::query()->find((int) $validated['financial_category_id']);

            if ($category === null || ! $category->active || $category->type === null) {
                throw ValidationException::withMessages([
                    'financial_category_id' => 'Categoria financeira invalida para recorrencia.',
                ]);
            }

            if ($category->type->value !== $validated['type']) {
                throw ValidationException::withMessages([
                    'type' => 'Tipo da recorrencia deve ser igual ao tipo da categoria.',
                ]);
            }

            $recurrence = Recurrence::query()->create([
                'company_id' => $company->getKey(),
                'financial_category_id' => (int) $validated['financial_category_id'],
                'type' => $validated['type'],
                'description' => $validated['description'],
                'document_number' => $validated['document_number'] ?? null,
                'amount_cents' => (int) $validated['amount_cents'],
                'frequency' => $validated['frequency'],
                'day_of_month' => $validated['day_of_month'] ?? null,
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'] ?? null,
                'installments' => $validated['installments'] ?? null,
                'installments_generated' => 0,
                'vehicle_id' => $validated['vehicle_id'] ?? null,
                'driver_id' => $validated['driver_id'] ?? null,
                'trip_id' => $validated['trip_id'] ?? null,
                'payment_method' => $validated['payment_method'] ?? null,
                'active' => $validated['active'] ?? true,
                'created_by' => $createdBy,
            ]);

            return (int) $recurrence->getKey();
        });
    }
}
