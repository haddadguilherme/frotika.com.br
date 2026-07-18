<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenancy;

use App\Support\Cnpj\Cnpj;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class RegisterOwnerAndCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<Closure|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'group_name' => ['required', 'string', 'max:120'],
            'company_legal_name' => ['required', 'string', 'max:150'],
            'company_trade_name' => ['required', 'string', 'max:150'],
            'company_cnpj' => [
                'required',
                'string',
                'size:14',
                'unique:companies,cnpj',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! Cnpj::isValid($value)) {
                        $fail('O CNPJ da empresa informado e invalido.');
                    }
                },
            ],
            'tax_regime' => ['nullable', 'string', 'in:simples,presumido,real'],
            'company_zip_code' => ['nullable', 'string', 'max:10'],
            'company_street' => ['nullable', 'string', 'max:150'],
            'company_number' => ['nullable', 'string', 'max:20'],
            'company_complement' => ['nullable', 'string', 'max:80'],
            'company_district' => ['nullable', 'string', 'max:80'],
            'company_city' => ['nullable', 'string', 'max:80'],
            'company_state' => ['nullable', 'string', 'size:2'],
            'company_phone' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'company_email' => ['nullable', 'email', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute e obrigatorio.',
            'email.email' => 'Informe um e-mail valido.',
            'email.unique' => 'Este e-mail ja esta cadastrado.',
            'password.min' => 'A senha deve ter ao menos :min caracteres.',
            'company_cnpj.size' => 'O CNPJ da empresa deve ter 14 digitos.',
            'company_cnpj.unique' => 'Este CNPJ ja esta cadastrado.',
            'tax_regime.in' => 'Regime tributario invalido.',
            'company_phone.regex' => 'Informe um telefone com DDD (10 ou 11 digitos).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'email' => 'e-mail',
            'password' => 'senha',
            'group_name' => 'nome do grupo',
            'company_legal_name' => 'razao social',
            'company_trade_name' => 'nome fantasia',
            'company_cnpj' => 'CNPJ da empresa',
            'tax_regime' => 'regime tributario',
            'company_zip_code' => 'CEP da empresa',
            'company_street' => 'logradouro da empresa',
            'company_number' => 'numero da empresa',
            'company_complement' => 'complemento da empresa',
            'company_district' => 'bairro da empresa',
            'company_city' => 'cidade da empresa',
            'company_state' => 'UF da empresa',
            'company_phone' => 'telefone da empresa',
            'company_email' => 'e-mail da empresa',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email', ''))),
            'company_cnpj' => Cnpj::digits((string) $this->input('company_cnpj', '')),
            'tax_regime' => (string) ($this->input('tax_regime', 'simples') ?: 'simples'),
            'company_legal_name' => trim((string) $this->input('company_legal_name', '')),
            'company_trade_name' => trim((string) $this->input('company_trade_name', '')),
            'company_zip_code' => $this->nullableTrimmed('company_zip_code'),
            'company_street' => $this->nullableTrimmed('company_street'),
            'company_number' => $this->nullableTrimmed('company_number'),
            'company_complement' => $this->nullableTrimmed('company_complement'),
            'company_district' => $this->nullableTrimmed('company_district'),
            'company_city' => $this->nullableTrimmed('company_city'),
            'company_state' => $this->nullableUpper('company_state'),
            'company_phone' => $this->nullableDigits('company_phone'),
            'company_email' => $this->nullableLower('company_email'),
        ]);
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : $value;
    }

    private function nullableDigits(string $key): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->input($key, '')) ?? '';

        return $digits === '' ? null : $digits;
    }

    private function nullableLower(string $key): ?string
    {
        $value = $this->nullableTrimmed($key);

        return $value === null ? null : mb_strtolower($value);
    }

    private function nullableUpper(string $key): ?string
    {
        $value = $this->nullableTrimmed($key);

        return $value === null ? null : mb_strtoupper($value);
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Os dados informados sao invalidos.',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
