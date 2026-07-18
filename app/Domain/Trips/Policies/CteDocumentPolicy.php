<?php

declare(strict_types=1);

namespace App\Domain\Trips\Policies;

use App\Domain\Trips\Models\CteDocument;
use App\Models\User;

final class CteDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->current_company_id !== null;
    }

    public function view(User $user, CteDocument $cte): bool
    {
        return $this->belongsToCurrentCompany($user, $cte);
    }

    public function create(User $user): bool
    {
        return $user->current_company_id !== null
            && $user->companies()->whereKey($user->current_company_id)->exists();
    }

    public function delete(User $user, CteDocument $cte): bool
    {
        return $this->belongsToCurrentCompany($user, $cte);
    }

    private function belongsToCurrentCompany(User $user, CteDocument $cte): bool
    {
        return (int) $cte->getAttribute('company_id') === (int) $user->current_company_id
            && $user->companies()->whereKey($user->current_company_id)->exists();
    }
}
