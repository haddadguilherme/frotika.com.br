<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Enums\FinancialEntryPaymentMethod;
use App\Domain\Finance\Enums\FinancialEntryStatus;
use App\Domain\Finance\Enums\FinancialEntryType;
use App\Domain\Fleet\Models\Driver;
use App\Domain\Fleet\Models\Vehicle;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property FinancialEntryType $type
 * @property FinancialEntryStatus $status
 * @property FinancialEntryPaymentMethod|null $payment_method
 */
class FinancialEntry extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => FinancialEntryType::class,
            'status' => FinancialEntryStatus::class,
            'payment_method' => FinancialEntryPaymentMethod::class,
            'competence_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'date',
            'reconciled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FinancialCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'financial_category_id');
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
