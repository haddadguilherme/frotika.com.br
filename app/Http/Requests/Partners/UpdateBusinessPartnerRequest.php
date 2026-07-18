<?php

declare(strict_types=1);

namespace App\Http\Requests\Partners;

use App\Domain\Partners\Models\BusinessPartner;
use Illuminate\Support\Facades\Gate;

final class UpdateBusinessPartnerRequest extends BusinessPartnerRequest
{
    public function authorize(): bool
    {
        $partner = BusinessPartner::query()->find($this->route('partner'));

        return $partner instanceof BusinessPartner && Gate::allows('update', $partner);
    }
}
