<?php

declare(strict_types=1);

namespace App\Domain\Fuelings\Data;

use App\Domain\Fuelings\Enums\FuelingPaymentMethod;
use App\Domain\Fuelings\Enums\FuelProduct;
use App\Domain\Fuelings\Enums\FuelTank;

final readonly class FuelingData
{
    public function __construct(
        public int $vehicleId,
        public string $fueledAt,
        public int $odometer,
        public FuelProduct $product,
        public float $liters,
        public FuelingPaymentMethod $paymentMethod,
        public FuelTank $tank = FuelTank::Main,
        public bool $fullTank = false,
        public ?float $pricePerLiter = null,
        public ?int $totalCents = null,
        public ?int $driverId = null,
        public ?int $supplierId = null,
        public ?string $stationName = null,
        public ?string $stationCity = null,
        public ?string $stationState = null,
        public ?string $invoiceNumber = null,
        public ?string $notes = null,
        public bool $allowOdometerRollback = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            vehicleId: (int) $data['vehicle_id'],
            fueledAt: (string) $data['fueled_at'],
            odometer: (int) $data['odometer'],
            product: FuelProduct::from((string) $data['product']),
            liters: (float) $data['liters'],
            paymentMethod: FuelingPaymentMethod::from((string) $data['payment_method']),
            tank: FuelTank::from((string) ($data['tank'] ?? 'main')),
            fullTank: (bool) ($data['full_tank'] ?? false),
            pricePerLiter: self::nullableFloat($data['price_per_liter'] ?? null),
            totalCents: self::nullableInt($data['total_cents'] ?? null),
            driverId: self::nullableInt($data['driver_id'] ?? null),
            supplierId: self::nullableInt($data['supplier_id'] ?? null),
            stationName: self::nullableString($data['station_name'] ?? null),
            stationCity: self::nullableString($data['station_city'] ?? null),
            stationState: self::nullableUpper($data['station_state'] ?? null),
            invoiceNumber: self::nullableString($data['invoice_number'] ?? null),
            notes: self::nullableString($data['notes'] ?? null),
            allowOdometerRollback: (bool) ($data['allow_odometer_rollback'] ?? false),
        );
    }

    /**
     * Resolve total e preço por litro: o usuário informa o total (obrigatório);
     * quando não informa o preço, deriva de total ÷ litros.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $totalCents = $this->totalCents ?? 0;
        $pricePerLiter = $this->pricePerLiter;

        if ($pricePerLiter === null && $this->liters > 0.0 && $totalCents > 0) {
            $pricePerLiter = round(($totalCents / 100) / $this->liters, 3);
        }

        return [
            'vehicle_id' => $this->vehicleId,
            'driver_id' => $this->driverId,
            'supplier_id' => $this->supplierId,
            'fueled_at' => $this->fueledAt,
            'odometer' => $this->odometer,
            'product' => $this->product->value,
            'liters' => $this->liters,
            'price_per_liter' => $pricePerLiter,
            'total_cents' => $totalCents,
            'full_tank' => $this->fullTank,
            'tank' => $this->tank->value,
            'station_name' => $this->stationName,
            'station_city' => $this->stationCity,
            'station_state' => $this->stationState,
            'invoice_number' => $this->invoiceNumber,
            'payment_method' => $this->paymentMethod->value,
            'notes' => $this->notes,
        ];
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function nullableUpper(mixed $value): ?string
    {
        $value = self::nullableString($value);

        return $value === null ? null : mb_strtoupper($value);
    }
}
