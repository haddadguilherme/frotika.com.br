<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenancy;

use App\Domain\Tenancy\Models\Company;
use App\Support\Cnpj\Cnpj;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Company::class);
    }

    /**
     * @return array<string, list<Closure|string>>
     */
    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:150'],
            'trade_name' => ['required', 'string', 'max:150'],
            'cnpj' => [
                'required',
                'string',
                'size:14',
                'unique:companies,cnpj',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! Cnpj::isValid($value)) {
                        $fail('O CNPJ informado é inválido.');
                    }
                },
            ],
            'tax_regime' => ['nullable', 'string', 'in:simples,presumido,real'],
            'state_registration' => ['nullable', 'string', 'max:20'],
            'rntrc' => ['nullable', 'string', 'max:12'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'street' => ['nullable', 'string', 'max:150'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'size:2'],
            'ibge_code' => ['nullable', 'string', 'size:7'],
            'phone' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'email' => ['nullable', 'email', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'cnpj.size' => 'O CNPJ deve ter 14 dígitos.',
            'cnpj.unique' => 'Este CNPJ já está cadastrado.',
            'tax_regime.in' => 'Regime tributário inválido.',
            'phone.regex' => 'Informe um telefone com DDD (10 ou 11 dígitos).',
            'email.email' => 'Informe um e-mail válido.',
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
            'cnpj' => 'CNPJ',
            'tax_regime' => 'regime tributário',
            'state_registration' => 'inscrição estadual',
            'rntrc' => 'RNTRC',
            'zip_code' => 'CEP',
            'street' => 'logradouro',
            'number' => 'número',
            'complement' => 'complemento',
            'district' => 'bairro',
            'city' => 'cidade',
            'state' => 'UF',
            'ibge_code' => 'código IBGE',
            'phone' => 'telefone',
            'email' => 'e-mail',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cnpj' => Cnpj::digits((string) $this->input('cnpj', '')),
            'tax_regime' => (string) ($this->input('tax_regime', 'simples') ?: 'simples'),
            'legal_name' => trim((string) $this->input('legal_name', '')),
            'trade_name' => trim((string) $this->input('trade_name', '')),
            'state_registration' => $this->nullableTrimmed('state_registration'),
            'rntrc' => $this->nullableTrimmed('rntrc'),
            'zip_code' => $this->nullableTrimmed('zip_code'),
            'street' => $this->nullableTrimmed('street'),
            'number' => $this->nullableTrimmed('number'),
            'complement' => $this->nullableTrimmed('complement'),
            'district' => $this->nullableTrimmed('district'),
            'city' => $this->nullableTrimmed('city'),
            'state' => $this->nullableUpper('state'),
            'ibge_code' => $this->nullableTrimmed('ibge_code'),
            'phone' => $this->nullableDigits('phone'),
            'email' => $this->nullableLower('email'),
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
