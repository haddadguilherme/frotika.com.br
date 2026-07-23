<?php

declare(strict_types=1);

namespace App\Http\Requests\Fleet;

use App\Domain\Fleet\Enums\VehicleBodyType;
use App\Domain\Fleet\Enums\VehicleFinancingType;
use App\Domain\Fleet\Enums\VehicleFuelType;
use App\Domain\Fleet\Enums\VehicleOwnership;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Enums\VehicleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class VehicleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $currentYear = (int) date('Y');

        return [
            'plate' => ['required', 'string', 'regex:/^[A-Z]{3}[0-9][0-9A-Z][0-9]{2}$/'],
            'type' => ['required', Rule::in($this->enumValues(VehicleType::cases()))],
            'status' => ['required', Rule::in($this->enumValues(VehicleStatus::cases()))],
            'ownership' => ['required', Rule::in($this->enumValues(VehicleOwnership::cases()))],
            'body_type' => ['nullable', Rule::in($this->enumValues(VehicleBodyType::cases()))],
            'fuel_type' => ['nullable', Rule::in($this->enumValues(VehicleFuelType::cases()))],
            'brand' => ['nullable', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:60'],
            'year_manufacture' => ['nullable', 'integer', 'between:1950,'.($currentYear + 1)],
            'year_model' => ['nullable', 'integer', 'between:1950,'.($currentYear + 1)],
            'renavam' => ['nullable', 'string', 'max:20'],
            'chassis' => ['nullable', 'string', 'max:30'],
            'rntrc' => ['nullable', 'string', 'max:12'],
            'engine_number' => ['nullable', 'string', 'max:60'],
            'axles' => ['nullable', 'integer', 'between:1,12'],
            'axle_distance_m' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'tire_count' => ['nullable', 'integer', 'between:1,24'],
            'tire_size' => ['nullable', 'string', 'max:20'],
            'tare_kg' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'capacity_kg' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'capacity_m3' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'tank_capacity_l' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'odometer_initial' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'acquisition_date' => ['nullable', 'date'],
            'acquisition_value_cents' => ['nullable', 'integer', 'min:0'],
            'crlv_due_at' => ['nullable', 'date'],
            'antt_due_at' => ['nullable', 'date'],
            'insurance_due_at' => ['nullable', 'date'],
            'is_financed' => ['nullable', 'boolean'],
            'financing_type' => ['nullable', Rule::in($this->enumValues(VehicleFinancingType::cases()))],
            'creditor_name' => ['nullable', 'string', 'max:120'],
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
            'plate.regex' => 'Informe uma placa válida (padrão antigo ABC1234 ou Mercosul ABC1D23).',
            'type.in' => 'Tipo de veículo inválido.',
            'status.in' => 'Situação inválida.',
            'ownership.in' => 'Tipo de propriedade inválido.',
            'body_type.in' => 'Tipo de carroceria inválido.',
            'fuel_type.in' => 'Tipo de combustível inválido.',
            'financing_type.in' => 'Tipo de financiamento inválido.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'plate' => 'placa',
            'type' => 'tipo',
            'status' => 'situação',
            'ownership' => 'propriedade',
            'body_type' => 'carroceria',
            'fuel_type' => 'combustível',
            'brand' => 'marca',
            'model' => 'modelo',
            'year_manufacture' => 'ano de fabricação',
            'year_model' => 'ano do modelo',
            'renavam' => 'RENAVAM',
            'chassis' => 'chassi',
            'rntrc' => 'RNTRC',
            'engine_number' => 'número do motor',
            'axles' => 'eixos',
            'axle_distance_m' => 'distância entre eixos',
            'tire_count' => 'quantidade de pneus',
            'tire_size' => 'medida de pneu',
            'tare_kg' => 'tara',
            'capacity_kg' => 'capacidade (kg)',
            'capacity_m3' => 'capacidade (m³)',
            'tank_capacity_l' => 'tanque',
            'odometer_initial' => 'hodômetro inicial',
            'acquisition_date' => 'data de aquisição',
            'acquisition_value_cents' => 'valor de aquisição',
            'crlv_due_at' => 'vencimento do CRLV',
            'antt_due_at' => 'vencimento da ANTT',
            'insurance_due_at' => 'vencimento do seguro',
            'is_financed' => 'é financiado',
            'financing_type' => 'tipo de financiamento',
            'creditor_name' => 'credor',
            'notes' => 'observações',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_financed' => $this->boolean('is_financed'),
            'plate' => $this->normalizePlate(),
            'type' => (string) ($this->input('type') ?: VehicleType::Tractor->value),
            'status' => (string) ($this->input('status') ?: VehicleStatus::Active->value),
            'ownership' => (string) ($this->input('ownership') ?: VehicleOwnership::Own->value),
            'body_type' => $this->nullableTrimmed('body_type'),
            'fuel_type' => $this->nullableTrimmed('fuel_type'),
            'brand' => $this->nullableTrimmed('brand'),
            'model' => $this->nullableTrimmed('model'),
            'renavam' => $this->nullableDigits('renavam'),
            'chassis' => $this->nullableUpper('chassis'),
            'rntrc' => $this->nullableDigits('rntrc'),
            'engine_number' => $this->nullableUpper('engine_number'),
            'axle_distance_m' => $this->nullableDecimal('axle_distance_m'),
            'tire_size' => $this->nullableUpper('tire_size'),
            'financing_type' => $this->nullableTrimmed('financing_type'),
            'creditor_name' => $this->nullableTrimmed('creditor_name'),
            'acquisition_value_cents' => $this->centsFromMoney('acquisition_value'),
            'notes' => $this->nullableTrimmed('notes'),
        ]);

        if (! $this->boolean('is_financed')) {
            $this->merge([
                'financing_type' => null,
                'creditor_name' => null,
            ]);
        }
    }

    /**
     * @param  list<VehicleType|VehicleStatus|VehicleOwnership|VehicleBodyType|VehicleFuelType|VehicleFinancingType>  $cases
     * @return list<string>
     */
    private function enumValues(array $cases): array
    {
        return array_map(static fn ($case): string => $case->value, $cases);
    }

    private function normalizePlate(): string
    {
        $raw = strtoupper((string) $this->input('plate', ''));

        return preg_replace('/[^A-Z0-9]/', '', $raw) ?? '';
    }

    protected function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : $value;
    }

    protected function nullableUpper(string $key): ?string
    {
        $value = $this->nullableTrimmed($key);

        return $value === null ? null : mb_strtoupper($value);
    }

    protected function nullableDigits(string $key): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->input($key, '')) ?? '';

        return $digits === '' ? null : $digits;
    }

    protected function nullableDecimal(string $key): ?float
    {
        $raw = trim((string) $this->input($key, ''));

        if ($raw === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $raw);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    /**
     * Converte um valor em reais (ex.: "150.000,00" ou "150000.00") para centavos.
     * Dinheiro é inteiro em centavos — nunca float na base (regra 1).
     */
    protected function centsFromMoney(string $key): ?int
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
            // pt-BR: ponto é milhar, vírgula é decimal.
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif ($hasComma) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return (int) round(((float) $normalized) * 100);
    }
}
