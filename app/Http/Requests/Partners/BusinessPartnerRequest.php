<?php

declare(strict_types=1);

namespace App\Http\Requests\Partners;

use App\Domain\Partners\Enums\BusinessPartnerKind;
use App\Support\Cnpj\Cnpj;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

abstract class BusinessPartnerRequest extends FormRequest
{
    /**
     * @return array<string, list<Closure|string|In>>
     */
    public function rules(): array
    {
        $kinds = array_map(static fn (BusinessPartnerKind $kind): string => $kind->value, BusinessPartnerKind::cases());

        return [
            'legal_name' => ['required', 'string', 'max:150'],
            'trade_name' => ['nullable', 'string', 'max:150'],
            'kind' => ['required', 'string', Rule::in($kinds)],
            'document' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    $length = strlen($value);

                    if ($length === 14 && ! Cnpj::isValid($value)) {
                        $fail('O CNPJ informado é inválido.');

                        return;
                    }

                    if ($length !== 14 && $length !== 11) {
                        $fail('Informe um CNPJ (14 dígitos) ou CPF (11 dígitos).');
                    }
                },
            ],
            'default_freight_share_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'state_registration' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'street' => ['nullable', 'string', 'max:150'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'size:2'],
            'ibge_code' => ['nullable', 'string', 'size:7'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'kind.in' => 'Tipo de parceiro inválido.',
            'phone.regex' => 'Informe um telefone com DDD (10 ou 11 dígitos).',
            'email.email' => 'Informe um e-mail válido.',
            'default_freight_share_percent.max' => 'O percentual do frete não pode passar de 100%.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'legal_name' => 'razão social',
            'trade_name' => 'nome fantasia',
            'kind' => 'tipo',
            'document' => 'documento',
            'default_freight_share_percent' => 'percentual do frete',
            'state_registration' => 'inscrição estadual',
            'phone' => 'telefone',
            'email' => 'e-mail',
            'zip_code' => 'CEP',
            'street' => 'logradouro',
            'number' => 'número',
            'complement' => 'complemento',
            'district' => 'bairro',
            'city' => 'cidade',
            'state' => 'UF',
            'ibge_code' => 'código IBGE',
            'notes' => 'observações',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'legal_name' => trim((string) $this->input('legal_name', '')),
            'trade_name' => $this->nullableTrimmed('trade_name'),
            'kind' => (string) ($this->input('kind', BusinessPartnerKind::Other->value) ?: BusinessPartnerKind::Other->value),
            'document' => $this->nullableDigits('document'),
            'state_registration' => $this->nullableTrimmed('state_registration'),
            'phone' => $this->nullableDigits('phone'),
            'email' => $this->nullableLower('email'),
            'zip_code' => $this->nullableDigits('zip_code'),
            'street' => $this->nullableTrimmed('street'),
            'number' => $this->nullableTrimmed('number'),
            'complement' => $this->nullableTrimmed('complement'),
            'district' => $this->nullableTrimmed('district'),
            'city' => $this->nullableTrimmed('city'),
            'state' => $this->nullableUpper('state'),
            'ibge_code' => $this->nullableTrimmed('ibge_code'),
            'notes' => $this->nullableTrimmed('notes'),
            'active' => $this->boolean('active', true),
        ]);
    }

    protected function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : $value;
    }

    protected function nullableDigits(string $key): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->input($key, '')) ?? '';

        return $digits === '' ? null : $digits;
    }

    protected function nullableLower(string $key): ?string
    {
        $value = $this->nullableTrimmed($key);

        return $value === null ? null : mb_strtolower($value);
    }

    protected function nullableUpper(string $key): ?string
    {
        $value = $this->nullableTrimmed($key);

        return $value === null ? null : mb_strtoupper($value);
    }
}
