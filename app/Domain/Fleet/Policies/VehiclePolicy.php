<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Policies;

use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;

final class VehiclePolicy
{
    /**
     * @var list<string>
     */
    private const MANAGER_ROLES = ['owner', 'admin'];

    public function viewAny(User $user): bool
    {
        return $user->current_group_id !== null;
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $this->sharesGroup($user, $vehicle);
    }

    public function create(User $user): bool
    {
        return $user->current_group_id !== null
            && $this->manages($user, (int) $user->current_group_id);
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        $groupId = $this->groupIdOf($vehicle);

        return $groupId !== null && $this->manages($user, $groupId);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->update($user, $vehicle);
    }

    private function sharesGroup(User $user, Vehicle $vehicle): bool
    {
        $groupId = $this->groupIdOf($vehicle);

        return $groupId !== null && $user->groups()->whereKey($groupId)->exists();
    }

    private function groupIdOf(Vehicle $vehicle): ?int
    {
        $company = Company::query()->find($vehicle->getAttribute('company_id'));

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
