<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Models;

use App\Domain\Fleet\Enums\CnhCategory;
use App\Domain\Fleet\Enums\DriverStatus;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property CnhCategory|null $cnh_category
 * @property DriverStatus $status
 * @property Carbon|null $cnh_expires_at
 */
final class Driver extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    /** Janela de antecedência do alerta de vencimento da CNH (blueprint 5.2). */
    public const CNH_ALERT_DAYS = 30;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cnh_category' => CnhCategory::class,
            'status' => DriverStatus::class,
            'cnh_expires_at' => 'date',
        ];
    }

    /**
     * Dias até o vencimento da CNH (negativo = vencida, null = sem data).
     */
    public function cnhDaysToExpire(): ?int
    {
        $expiresAt = $this->cnh_expires_at;

        if (! $expiresAt instanceof Carbon) {
            return null;
        }

        return (int) Carbon::today()->diffInDays($expiresAt->startOfDay(), false);
    }

    /**
     * Estado do alerta: 'expired', 'expiring' (dentro da janela) ou null (ok / sem data).
     */
    public function cnhAlert(): ?string
    {
        $days = $this->cnhDaysToExpire();

        if ($days === null) {
            return null;
        }

        if ($days < 0) {
            return 'expired';
        }

        return $days <= self::CNH_ALERT_DAYS ? 'expiring' : null;
    }
}
