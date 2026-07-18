<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Enums\BankAccountType;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property BankAccountType $type
 */
class BankAccount extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => BankAccountType::class,
            'initial_balance_at' => 'date',
            'is_default' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
