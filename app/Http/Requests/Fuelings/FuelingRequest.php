<?php

declare(strict_types=1);

namespace App\Http\Requests\Fuelings;

use App\Domain\Fuelings\Enums\FuelingPaymentMethod;
use App\Domain\Fuelings\Enums\FuelProduct;
use App\Domain\Fuelings\Enums\FuelTank;
use App\Support\Money\Brl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class FuelingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'integer', 'min:1'],
            'driver_id' => ['nullable', 'integer', 'min:1'],
            'supplier_id' => ['nullable', 'integer', 'min:1'],
            'fueled_at' => ['required', 'date'],
            'odometer' => ['required', 'integer', 'min:0', 'max:9999999'],
            'product' => ['required', Rule::in($this->enumValues(FuelProduct::cases()))],
            'liters' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'price_per_liter' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'total_cents' => ['required', 'integer', 'min:1'],
            'full_tank' => ['boolean'],
            'tank' => ['required', Rule::in($this->enumValues(FuelTank::cases()))],
            'payment_method' => ['required', Rule::in($this->enumValues(FuelingPaymentMethod::cases()))],
            'station_name' => ['nullable', 'string', 'max:120'],
            'station_city' => ['nullable', 'string', 'max:80'],
            'station_state' => ['nullable', 'string', 'size:2'],
            'invoice_number' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allow_odometer_rollback' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'product.in' => 'Produto inválido.',
            'tank.in' => 'Tanque inválido.',
            'payment_method.in' => 'Forma de pagamento inválida.',
            'liters.gt' => 'Informe uma quantidade de litros maior que zero.',
            'total_cents.min' => 'Informe o valor total do abastecimento.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vehicle_id' => 'veículo',
            'driver_id' => 'motorista',
            'supplier_id' => 'posto',
            'fueled_at' => 'data',
            'odometer' => 'odômetro',
            'product' => 'produto',
            'liters' => 'litros',
            'price_per_liter' => 'preço por litro',
            'total_cents' => 'valor total',
            'tank' => 'tanque',
            'payment_method' => 'forma de pagamento',
            'station_name' => 'posto',
            'station_city' => 'cidade',
            'station_state' => 'UF',
            'invoice_number' => 'nota/cupom',
            'notes' => 'observações',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'driver_id' => $this->nullableId('driver_id'),
            'supplier_id' => $this->nullableId('supplier_id'),
            'liters' => $this->normalizeDecimal('liters'),
            'price_per_liter' => $this->normalizeDecimal('price_per_liter'),
            'total_cents' => Brl::toCents($this->input('total')),
            'product' => (string) ($this->input('product') ?: FuelProduct::DieselS10->value),
            'tank' => (string) ($this->input('tank') ?: FuelTank::Main->value),
            'payment_method' => (string) ($this->input('payment_method') ?: FuelingPaymentMethod::Cash->value),
            'full_tank' => $this->boolean('full_tank'),
            'allow_odometer_rollback' => $this->boolean('allow_odometer_rollback'),
            'station_name' => $this->nullableTrimmed('station_name'),
            'station_city' => $this->nullableTrimmed('station_city'),
            'station_state' => $this->nullableUpper('station_state'),
            'invoice_number' => $this->nullableTrimmed('invoice_number'),
            'notes' => $this->nullableTrimmed('notes'),
        ]);
    }

    /**
     * @param  list<FuelProduct|FuelTank|FuelingPaymentMethod>  $cases
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

    protected function nullableUpper(string $key): ?string
    {
        $value = $this->nullableTrimmed($key);

        return $value === null ? null : mb_strtoupper($value);
    }
}
