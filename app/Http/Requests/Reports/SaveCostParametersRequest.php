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
            'default.tire_set_price' => ['nullable', 'string', 'max:20'],
            'default.tire_life_km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'default.oil_change_cost' => ['nullable', 'string', 'max:20'],
            'default.oil_interval_km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'default.prudential_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default.driver_salary' => ['nullable', 'string', 'max:20'],
            'default.owner_prolabore' => ['nullable', 'string', 'max:20'],

            'vehicles' => ['array'],
            'vehicles.*.tire_set_price' => ['nullable', 'string', 'max:20'],
            'vehicles.*.tire_life_km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'vehicles.*.oil_change_cost' => ['nullable', 'string', 'max:20'],
            'vehicles.*.oil_interval_km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'vehicles.*.prudential_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vehicles.*.driver_salary' => ['nullable', 'string', 'max:20'],
            'vehicles.*.owner_prolabore' => ['nullable', 'string', 'max:20'],
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
     * Campos vazios viram null (input number vazio manda ""), e o percentual
     * pode vir com vírgula do teclado pt-BR.
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

        if (isset($row['prudential_percent']) && is_string($row['prudential_percent'])) {
            $row['prudential_percent'] = str_replace(',', '.', $row['prudential_percent']);
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
