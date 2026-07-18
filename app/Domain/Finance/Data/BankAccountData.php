<?php

declare(strict_types=1);

namespace App\Domain\Finance\Data;

use App\Domain\Finance\Enums\BankAccountType;

final readonly class BankAccountData
{
    public function __construct(
        public string $name,
        public BankAccountType $type,
        public int $initialBalanceCents = 0,
        public ?string $initialBalanceAt = null,
        public ?string $bankCode = null,
        public ?string $agency = null,
        public ?string $number = null,
        public bool $isDefault = false,
        public bool $active = true,
        public ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            type: BankAccountType::from((string) $data['type']),
            initialBalanceCents: (int) ($data['initial_balance_cents'] ?? 0),
            initialBalanceAt: self::nullableString($data['initial_balance_at'] ?? null),
            bankCode: self::nullableString($data['bank_code'] ?? null),
            agency: self::nullableString($data['agency'] ?? null),
            number: self::nullableString($data['number'] ?? null),
            isDefault: (bool) ($data['is_default'] ?? false),
            active: (bool) ($data['active'] ?? true),
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
            'type' => $this->type->value,
            'initial_balance_cents' => $this->initialBalanceCents,
            'initial_balance_at' => $this->initialBalanceAt,
            'bank_code' => $this->bankCode,
            'agency' => $this->agency,
            'number' => $this->number,
            'is_default' => $this->isDefault,
            'active' => $this->active,
            'notes' => $this->notes,
        ];
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
