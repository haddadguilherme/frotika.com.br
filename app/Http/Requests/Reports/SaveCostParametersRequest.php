<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Domain\Fleet\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class SaveCostParametersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Vehicle::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'default' => ['array'],
            'default.oil_reserve_per_km' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'default.tire_reserve_per_km' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'default.prudential_reserve_per_km' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'default.driver_salary' => ['nullable', 'string', 'max:20'],
            'default.prolabore_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'vehicles' => ['array'],
            'vehicles.*.oil_reserve_per_km' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'vehicles.*.tire_reserve_per_km' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'vehicles.*.prudential_reserve_per_km' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'vehicles.*.driver_salary' => ['nullable', 'string', 'max:20'],
            'vehicles.*.prolabore_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'default' => $this->normalizeRow($this->input('default')),
            'vehicles' => $this->normalizeVehicles($this->input('vehicles')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultFields(): array
    {
        $default = $this->validated('default');

        return is_array($default) ? $default : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function vehicleFields(): array
    {
        $vehicles = $this->validated('vehicles');

        if (! is_array($vehicles)) {
            return [];
        }

        $result = [];

        foreach ($vehicles as $vehicleId => $fields) {
            if (is_numeric($vehicleId) && is_array($fields)) {
                $result[(int) $vehicleId] = $fields;
            }
        }

        return $result;
    }

    /**
     * @var list<string>
     */
    private const DECIMAL_FIELDS = [
        'oil_reserve_per_km',
        'tire_reserve_per_km',
        'prudential_reserve_per_km',
        'prolabore_percent',
    ];

    /**
     * Campos vazios viram null (input number vazio manda ""), e os decimais
     * podem vir com vírgula do teclado pt-BR.
     */
    private function normalizeRow(mixed $row): mixed
    {
        if (! is_array($row)) {
            return $row;
        }

        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                $row[$key] = $value === '' ? null : $value;
            }
        }

        foreach (self::DECIMAL_FIELDS as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $row[$field] = str_replace(',', '.', $row[$field]);
            }
        }

        return $row;
    }

    private function normalizeVehicles(mixed $vehicles): mixed
    {
        if (! is_array($vehicles)) {
            return $vehicles;
        }

        return array_map(fn (mixed $row): mixed => $this->normalizeRow($row), $vehicles);
    }
}
