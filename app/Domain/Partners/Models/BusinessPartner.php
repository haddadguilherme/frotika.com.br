<?php

declare(strict_types=1);

namespace App\Domain\Partners\Models;

use App\Domain\Partners\Enums\BusinessPartnerDocumentType;
use App\Domain\Partners\Enums\BusinessPartnerKind;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property BusinessPartnerKind $kind
 * @property BusinessPartnerDocumentType $document_type
 */
final class BusinessPartner extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => BusinessPartnerKind::class,
            'document_type' => BusinessPartnerDocumentType::class,
            'default_freight_share_percent' => 'decimal:2',
            'active' => 'boolean',
        ];
    }
}
