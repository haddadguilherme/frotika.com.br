<?php

declare(strict_types=1);

namespace App\Http\Requests\Maintenances;

use App\Domain\Maintenances\Enums\MaintenanceCategory;
use App\Domain\Maintenances\Enums\MaintenanceStatus;
use App\Domain\Maintenances\Enums\MaintenanceType;
use App\Support\Money\Brl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class MaintenanceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'integer', 'min:1'],
            'supplier_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['required', Rule::in($this->enumValues(MaintenanceType::cases()))],
            'category' => ['required', Rule::in($this->enumValues(MaintenanceCategory::cases()))],
            'status' => ['required', Rule::in($this->enumValues(MaintenanceStatus::cases()))],
            'opened_at' => ['required', 'date'],
            'closed_at' => ['nullable', 'date', 'after_or_equal:opened_at', 'required_if:status,completed'],
            'odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'workshop_name' => ['nullable', 'string', 'max:120'],
            'invoice_number' => ['nullable', 'string', 'max:60'],
            'labor_cents' => ['required', 'integer', 'min:0'],
            'parts_cents' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:5000'],
            'downtime_hours' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'next_service_odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'next_service_at' => ['nullable', 'date'],
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
            'type.in' => 'Tipo de manutenção inválido.',
            'category.in' => 'Categoria inválida.',
            'status.in' => 'Situação inválida.',
            'closed_at.after_or_equal' => 'A data de conclusão não pode ser anterior à abertura.',
            'closed_at.required_if' => 'Informe a data de conclusão para uma manutenção concluída.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vehicle_id' => 'veículo',
            'supplier_id' => 'oficina',
            'type' => 'tipo',
            'category' => 'categoria',
            'status' => 'situação',
            'opened_at' => 'abertura',
            'closed_at' => 'conclusão',
            'odometer' => 'odômetro',
            'workshop_name' => 'oficina',
            'invoice_number' => 'nota',
            'labor_cents' => 'mão de obra',
            'parts_cents' => 'peças',
            'description' => 'descrição',
            'downtime_hours' => 'horas paradas',
            'next_service_odometer' => 'odômetro da próxima revisão',
            'next_service_at' => 'data da próxima revisão',
            'notes' => 'observações',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'supplier_id' => $this->nullableId('supplier_id'),
            'type' => (string) ($this->input('type') ?: MaintenanceType::Corrective->value),
            'category' => (string) ($this->input('category') ?: MaintenanceCategory::Other->value),
            'status' => (string) ($this->input('status') ?: MaintenanceStatus::Open->value),
            'labor_cents' => Brl::toCents($this->input('labor')) ?? 0,
            'parts_cents' => Brl::toCents($this->input('parts')) ?? 0,
            'downtime_hours' => $this->normalizeDecimal('downtime_hours'),
            'workshop_name' => $this->nullableTrimmed('workshop_name'),
            'invoice_number' => $this->nullableTrimmed('invoice_number'),
            'description' => $this->nullableTrimmed('description'),
            'notes' => $this->nullableTrimmed('notes'),
        ]);
    }

    /**
     * @param  list<MaintenanceType|MaintenanceCategory|MaintenanceStatus>  $cases
     * @return list<string>
     */
    private function enumValues(array $cases): array
    {
        return array_map(static fn ($case): string => $case->value, $cases);
    }

    private function normalizeDecimal(string $key): ?string
    {
        $raw = trim((string) $this->input($key, ''));

        if ($raw === '') {
            return null;
        }

        $normalized = preg_replace('/[^\d,.-]/', '', $raw) ?? '';

        if ($normalized === '') {
            return null;
        }

        $hasComma = str_contains($normalized, ',');
        $hasDot = str_contains($normalized, '.');

        if ($hasComma && $hasDot) {
            $normalized = str_replace(['.', ','], ['', '.'], $normalized);
        } elseif ($hasComma) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return $normalized;
    }

    protected function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : $value;
    }

    protected function nullableId(string $key): ?int
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : (int) $value;
    }
}
