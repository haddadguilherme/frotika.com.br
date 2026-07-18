<?php

declare(strict_types=1);

namespace App\Domain\Maintenances\Models;

use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Maintenances\Enums\MaintenanceCategory;
use App\Domain\Maintenances\Enums\MaintenanceStatus;
use App\Domain\Maintenances\Enums\MaintenanceType;
use App\Domain\Partners\Models\BusinessPartner;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property MaintenanceType $type
 * @property MaintenanceCategory $category
 * @property MaintenanceStatus $status
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 */
final class Maintenance extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => MaintenanceType::class,
            'category' => MaintenanceCategory::class,
            'status' => MaintenanceStatus::class,
            'opened_at' => 'date',
            'closed_at' => 'date',
            'odometer' => 'integer',
            'labor_cents' => 'integer',
            'parts_cents' => 'integer',
            'total_cents' => 'integer',
            'downtime_hours' => 'decimal:2',
            'next_service_odometer' => 'integer',
            'next_service_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    /**
     * Oficina que executou o serviço (parceiro comercial, kind = workshop).
     *
     * @return BelongsTo<BusinessPartner, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(BusinessPartner::class, 'supplier_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
