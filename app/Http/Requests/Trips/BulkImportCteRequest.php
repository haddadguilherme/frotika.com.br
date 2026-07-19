<?php

declare(strict_types=1);

namespace App\Http\Requests\Trips;

use App\Domain\Trips\Models\CteDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class BulkImportCteRequest extends FormRequest
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
            'xmls' => ['required', 'array', 'min:1', 'max:20'],
            'xmls.*' => ['required', 'file', 'max:4096', 'extensions:xml,txt'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'xmls.required' => 'Selecione ao menos um arquivo XML de CT-e.',
            'xmls.min' => 'Selecione ao menos um arquivo XML de CT-e.',
            'xmls.max' => 'Envie no máximo 20 arquivos por vez.',
            'xmls.*.required' => 'Selecione um arquivo válido.',
            'xmls.*.file' => 'Envie um arquivo válido.',
            'xmls.*.max' => 'Cada arquivo pode ter no máximo 4 MB.',
            'xmls.*.extensions' => 'Cada arquivo precisa ter extensão .xml.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'xmls' => 'arquivos XML',
            'xmls.*' => 'arquivo XML',
        ];
    }
}
