<?php

declare(strict_types=1);

namespace App\Domain\Partners\Policies;

use App\Domain\Partners\Models\BusinessPartner;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;

final class BusinessPartnerPolicy
{
    /**
     * @var list<string>
     */
    private const MANAGER_ROLES = ['owner', 'admin'];

    public function viewAny(User $user): bool
    {
        return $user->current_group_id !== null;
    }

    public function view(User $user, BusinessPartner $partner): bool
    {
        return $this->sharesGroup($user, $partner);
    }

    public function create(User $user): bool
    {
        return $user->current_group_id !== null
            && $this->manages($user, (int) $user->current_group_id);
    }

    public function update(User $user, BusinessPartner $partner): bool
    {
        $groupId = $this->groupIdOf($partner);

        return $groupId !== null && $this->manages($user, $groupId);
    }

    public function delete(User $user, BusinessPartner $partner): bool
    {
        return $this->update($user, $partner);
    }

    private function sharesGroup(User $user, BusinessPartner $partner): bool
    {
        $groupId = $this->groupIdOf($partner);

        return $groupId !== null && $user->groups()->whereKey($groupId)->exists();
    }

    private function groupIdOf(BusinessPartner $partner): ?int
    {
        $company = Company::query()->find($partner->getAttribute('company_id'));

        return $company === null ? null : (int) $company->getAttribute('group_id');
    }

    private function manages(User $user, int $groupId): bool
    {
        return $user->groups()
            ->whereKey($groupId)
            ->wherePivotIn('role', self::MANAGER_ROLES)
            ->exists();
    }
}
