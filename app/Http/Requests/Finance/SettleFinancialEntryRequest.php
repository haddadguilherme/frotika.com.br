<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Domain\Finance\Enums\FinancialEntryPaymentMethod;
use App\Domain\Finance\Models\FinancialEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class SettleFinancialEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = FinancialEntry::query()->find($this->route('entry'));

        return $entry instanceof FinancialEntry && Gate::allows('update', $entry);
    }

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
            'bank_account_id' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'payment_method' => ['nullable', Rule::in($methods)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'bank_account_id' => 'conta bancária',
            'paid_at' => 'data de pagamento',
            'payment_method' => 'meio de pagamento',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_method' => ($value = trim((string) $this->input('payment_method', ''))) === '' ? null : $value,
        ]);
    }
}
