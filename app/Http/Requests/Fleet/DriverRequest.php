<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet;

use App\Domain\Fleet\Enums\CnhCategory;
use App\Domain\Fleet\Enums\DriverStatus;
use App\Support\Cpf\Cpf;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class DriverRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'cpf' => [
                'required',
                'string',
                'size:11',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! Cpf::isValid($value)) {
                        $fail('Informe um CPF válido.');
                    }
                },
            ],
            'cnh_number' => ['nullable', 'string', 'max:20'],
            'cnh_category' => ['nullable', Rule::in(array_map(static fn (CnhCategory $c): string => $c->value, CnhCategory::cases()))],
            'cnh_expires_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_map(static fn (DriverStatus $s): string => $s->value, DriverStatus::cases()))],
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
            'cpf.size' => 'O CPF deve ter 11 dígitos.',
            'cnh_category.in' => 'Categoria de CNH inválida.',
            'status.in' => 'Situação inválida.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'cpf' => 'CPF',
            'cnh_number' => 'número da CNH',
            'cnh_category' => 'categoria da CNH',
            'cnh_expires_at' => 'vencimento da CNH',
            'status' => 'situação',
            'notes' => 'observações',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cpf' => Cpf::digits((string) $this->input('cpf', '')),
            'cnh_number' => $this->nullableTrimmed('cnh_number'),
            'status' => (string) ($this->input('status') ?: DriverStatus::Active->value),
            'notes' => $this->nullableTrimmed('notes'),
        ]);
    }

    protected function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : $value;
    }
}
