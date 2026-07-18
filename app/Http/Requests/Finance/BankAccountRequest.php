<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Domain\Finance\Enums\BankAccountType;
use App\Support\Money\Brl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class BankAccountRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $types = array_map(static fn (BankAccountType $type): string => $type->value, BankAccountType::cases());

        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in($types)],
            'initial_balance_cents' => ['required', 'integer'],
            'initial_balance_at' => ['nullable', 'date'],
            'bank_code' => ['nullable', 'string', 'max:10'],
            'agency' => ['nullable', 'string', 'max:20'],
            'number' => ['nullable', 'string', 'max:30'],
            'is_default' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'type.in' => 'Tipo de conta inválido.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'type' => 'tipo',
            'initial_balance_cents' => 'saldo inicial',
            'initial_balance_at' => 'data do saldo inicial',
            'bank_code' => 'banco',
            'agency' => 'agência',
            'number' => 'conta',
            'notes' => 'observações',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'type' => (string) ($this->input('type') ?: BankAccountType::Cash->value),
            'initial_balance_cents' => Brl::toCents($this->input('initial_balance')) ?? 0,
            'bank_code' => $this->nullableTrimmed('bank_code'),
            'agency' => $this->nullableTrimmed('agency'),
            'number' => $this->nullableTrimmed('number'),
            'is_default' => $this->boolean('is_default'),
            'active' => $this->boolean('active', true),
            'notes' => $this->nullableTrimmed('notes'),
        ]);
    }

    protected function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : $value;
    }
}
