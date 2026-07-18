<?php

declare(strict_types=1);

namespace App\Domain\Trips\Models;

use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Partners\Models\BusinessPartner;
use App\Domain\Trips\Enums\CteServiceType;
use App\Domain\Trips\Enums\CteStatus;
use App\Domain\Trips\Enums\CteTakerRole;
use App\Domain\Trips\Enums\CteType;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property CteType $cte_type
 * @property CteServiceType $service_type
 * @property CteStatus $status
 * @property CteTakerRole|null $taker_role
 * @property Carbon $issued_at
 * @property Carbon|null $imported_at
 */
final class CteDocument extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cte_type' => CteType::class,
            'service_type' => CteServiceType::class,
            'status' => CteStatus::class,
            'taker_role' => CteTakerRole::class,
            'issued_at' => 'datetime',
            'imported_at' => 'datetime',
            'total_value_cents' => 'integer',
            'receivable_value_cents' => 'integer',
            'icms_value_cents' => 'integer',
            'cargo_value_cents' => 'integer',
            'cargo_weight_kg' => 'decimal:3',
            'applied_share_percent' => 'decimal:2',
            'raw' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'trailer_vehicle_id');
    }

    /**
     * @return BelongsToMany<BusinessPartner, $this>
     */
    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(BusinessPartner::class, 'cte_document_business_partner')
            ->withPivot('role')
            ->withTimestamps();
    }
}
