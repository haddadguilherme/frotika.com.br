<?php

declare(strict_types=1);

namespace App\Platform\Http\Requests;

use App\Models\User;
use App\Support\Money\Brl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

final class IssueGroupLicenseInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isPlatformAdmin();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount_reais' => [
                'required',
                'string',
                'max:30',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $amountCents = Brl::toCents(is_scalar($value) ? (string) $value : null);

                    if ($amountCents === null || $amountCents < 1) {
                        $fail('Informe o valor da mensalidade em reais (ex.: 129,90).');
                    }
                },
            ],
            'due_date' => ['required', 'date'],
            'reference_month' => ['nullable', 'date_format:Y-m'],
            'boleto_number' => ['nullable', 'string', 'max:100'],
            'boleto_url' => ['nullable', 'url', 'max:2048'],
            'boleto_pdf_url' => ['nullable', 'url', 'max:2048'],
            'boleto_file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount_reais.required' => 'Informe o valor da mensalidade em reais.',
            'amount_reais.max' => 'O valor da mensalidade está muito longo.',
            'due_date.required' => 'Informe a data de vencimento do boleto.',
            'due_date.date' => 'Data de vencimento inválida.',
            'reference_month.date_format' => 'A competência deve estar no formato AAAA-MM.',
            'boleto_url.url' => 'A URL do boleto está inválida.',
            'boleto_pdf_url.url' => 'A URL do PDF do boleto está inválida.',
            'boleto_file.file' => 'Envie um arquivo válido para o boleto.',
            'boleto_file.max' => 'O arquivo do boleto deve ter no máximo 10 MB.',
            'boleto_file.mimes' => 'O arquivo do boleto deve ser PDF, JPG, JPEG ou PNG.',
        ];
    }

    public function amountCents(): int
    {
        $amountReais = $this->validated('amount_reais');
        $amountCents = Brl::toCents(is_scalar($amountReais) ? (string) $amountReais : null);

        if ($amountCents === null || $amountCents < 1) {
            throw ValidationException::withMessages([
                'amount_reais' => 'Informe o valor da mensalidade em reais (ex.: 129,90).',
            ]);
        }

        return $amountCents;
    }
}
