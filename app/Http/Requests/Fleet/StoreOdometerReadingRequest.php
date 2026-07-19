<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet;

use Illuminate\Foundation\Http\FormRequest;

final class StoreOdometerReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'read_on' => ['required', 'date'],
            'odometer' => ['required', 'integer', 'min:0', 'max:9999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'read_on' => 'data da leitura',
            'odometer' => 'hodômetro',
            'note' => 'observação',
        ];
    }

    protected function prepareForValidation(): void
    {
        $note = trim((string) $this->input('note', ''));

        $this->merge([
            'note' => $note === '' ? null : $note,
        ]);
    }
}
