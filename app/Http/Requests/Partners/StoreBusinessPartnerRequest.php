<?php

declare(strict_types=1);

namespace App\Http\Requests\Partners;

use App\Domain\Partners\Models\BusinessPartner;
use Illuminate\Support\Facades\Gate;

final class StoreBusinessPartnerRequest extends BusinessPartnerRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', BusinessPartner::class);
    }
}
