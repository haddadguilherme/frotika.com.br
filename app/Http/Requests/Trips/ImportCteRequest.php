<?php

declare(strict_types=1);

namespace App\Http\Requests\Trips;

use App\Domain\Trips\Models\CteDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ImportCteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', CteDocument::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'xml' => ['required', 'file', 'max:4096', 'extensions:xml,txt'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'xml.required' => 'Selecione o arquivo XML do CT-e.',
            'xml.file' => 'Envie um arquivo válido.',
            'xml.max' => 'O arquivo pode ter no máximo 4 MB.',
            'xml.extensions' => 'O arquivo precisa ter extensão .xml.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'xml' => 'arquivo XML',
        ];
    }
}
