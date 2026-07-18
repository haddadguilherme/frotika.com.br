<?php

declare(strict_types=1);

namespace App\Domain\Maintenances\Data;

use App\Domain\Maintenances\Enums\MaintenanceCategory;
use App\Domain\Maintenances\Enums\MaintenanceStatus;
use App\Domain\Maintenances\Enums\MaintenanceType;

final readonly class MaintenanceData
{
    public function __construct(
        public int $vehicleId,
        public MaintenanceType $type,
        public MaintenanceCategory $category,
        public MaintenanceStatus $status,
        public string $openedAt,
        public int $laborCents = 0,
        public int $partsCents = 0,
        public ?int $supplierId = null,
        public ?string $closedAt = null,
        public ?int $odometer = null,
        public ?string $workshopName = null,
        public ?string $invoiceNumber = null,
        public ?string $description = null,
        public ?float $downtimeHours = null,
        public ?int $nextServiceOdometer = null,
        public ?string $nextServiceAt = null,
        public ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            vehicleId: (int) $data['vehicle_id'],
            type: MaintenanceType::from((string) $data['type']),
            category: MaintenanceCategory::from((string) $data['category']),
            status: MaintenanceStatus::from((string) $data['status']),
            openedAt: (string) $data['opened_at'],
            laborCents: self::nullableInt($data['labor_cents'] ?? null) ?? 0,
            partsCents: self::nullableInt($data['parts_cents'] ?? null) ?? 0,
            supplierId: self::nullableInt($data['supplier_id'] ?? null),
            closedAt: self::nullableString($data['closed_at'] ?? null),
            odometer: self::nullableInt($data['odometer'] ?? null),
            workshopName: self::nullableString($data['workshop_name'] ?? null),
            invoiceNumber: self::nullableString($data['invoice_number'] ?? null),
            description: self::nullableString($data['description'] ?? null),
            downtimeHours: self::nullableFloat($data['downtime_hours'] ?? null),
            nextServiceOdometer: self::nullableInt($data['next_service_odometer'] ?? null),
            nextServiceAt: self::nullableString($data['next_service_at'] ?? null),
            notes: self::nullableString($data['notes'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'supplier_id' => $this->supplierId,
            'type' => $this->type->value,
            'category' => $this->category->value,
            'status' => $this->status->value,
            'opened_at' => $this->openedAt,
            'closed_at' => $this->closedAt,
            'odometer' => $this->odometer,
            'workshop_name' => $this->workshopName,
            'invoice_number' => $this->invoiceNumber,
            'labor_cents' => $this->laborCents,
            'parts_cents' => $this->partsCents,
            // total_cents = labor + parts (blueprint 5.5).
            'total_cents' => $this->laborCents + $this->partsCents,
            'description' => $this->description,
            'downtime_hours' => $this->downtimeHours,
            'next_service_odometer' => $this->nextServiceOdometer,
            'next_service_at' => $this->nextServiceAt,
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
}
