<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Domain\Finance\Enums\FinancialEntryPaymentMethod;
use App\Support\Money\Brl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class FinancialEntryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $methods = array_map(
            static fn (FinancialEntryPaymentMethod $method): string => $method->value,
            FinancialEntryPaymentMethod::cases(),
        );

        return [
            'financial_category_id' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:200'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'competence_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['forecast', 'settled'])],
            'bank_account_id' => ['nullable', 'integer', 'min:1', 'required_if:status,settled', 'prohibited_if:status,forecast'],
            'paid_at' => ['nullable', 'date', 'required_if:status,settled', 'prohibited_if:status,forecast'],
            'payment_method' => ['nullable', Rule::in($methods)],
            'vehicle_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'amount_cents.min' => 'O valor deve ser maior que zero.',
            'bank_account_id.required_if' => 'Lançamento liquidado exige uma conta bancária.',
            'bank_account_id.prohibited_if' => 'Lançamento previsto não pode ter conta bancária.',
            'paid_at.required_if' => 'Lançamento liquidado exige a data de pagamento.',
            'paid_at.prohibited_if' => 'Lançamento previsto não pode ter data de pagamento.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'financial_category_id' => 'categoria',
            'description' => 'descrição',
            'document_number' => 'documento',
            'competence_date' => 'data de competência',
            'due_date' => 'data de vencimento',
            'amount_cents' => 'valor',
            'status' => 'situação',
            'bank_account_id' => 'conta bancária',
            'paid_at' => 'data de pagamento',
            'payment_method' => 'meio de pagamento',
            'vehicle_id' => 'veículo',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => (string) ($this->input('status') ?: 'forecast'),
            'description' => trim((string) $this->input('description', '')),
            'document_number' => $this->nullableTrimmed('document_number'),
            'amount_cents' => Brl::toCents($this->input('amount')),
            'bank_account_id' => $this->nullableInt('bank_account_id'),
            'paid_at' => $this->input('paid_at') ?: null,
            'payment_method' => $this->nullableTrimmed('payment_method'),
            'vehicle_id' => $this->nullableInt('vehicle_id'),
            'due_date' => $this->input('due_date') ?: null,
        ]);
    }

    protected function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : $value;
    }

    protected function nullableInt(string $key): ?int
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
