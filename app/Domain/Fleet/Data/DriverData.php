<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Data;

use App\Domain\Fleet\Enums\CnhCategory;
use App\Domain\Fleet\Enums\DriverStatus;

final readonly class DriverData
{
    public function __construct(
        public string $name,
        public DriverStatus $status,
        public ?string $cpf = null,
        public ?string $cnhNumber = null,
        public ?CnhCategory $cnhCategory = null,
        public ?string $cnhExpiresAt = null,
        public ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim((string) $data['name']),
            status: DriverStatus::from((string) ($data['status'] ?? DriverStatus::Active->value)),
            cpf: self::nullableString($data['cpf'] ?? null),
            cnhNumber: self::nullableString($data['cnh_number'] ?? null),
            cnhCategory: self::nullableCategory($data['cnh_category'] ?? null),
            cnhExpiresAt: self::nullableString($data['cnh_expires_at'] ?? null),
            notes: self::nullableString($data['notes'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status->value,
            'cpf' => $this->cpf,
            'cnh_number' => $this->cnhNumber,
            'cnh_category' => $this->cnhCategory?->value,
            'cnh_expires_at' => $this->cnhExpiresAt,
            'notes' => $this->notes,
        ];
    }

    private static function nullableCategory(mixed $value): ?CnhCategory
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CnhCategory::tryFrom((string) $value);
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
