<?php

declare(strict_types=1);

namespace App\Domain\Finance\Policies;

use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;

final class FinancialEntryPolicy
{
    /**
     * @var list<string>
     */
    private const MANAGER_ROLES = ['owner', 'admin'];

    public function viewAny(User $user): bool
    {
        return $user->current_group_id !== null;
    }

    public function view(User $user, FinancialEntry $entry): bool
    {
        return $this->sharesGroup($user, $entry);
    }

    public function create(User $user): bool
    {
        return $user->current_group_id !== null
            && $this->manages($user, (int) $user->current_group_id);
    }

    public function update(User $user, FinancialEntry $entry): bool
    {
        $groupId = $this->groupIdOf($entry);

        return $groupId !== null && $this->manages($user, $groupId);
    }

    public function delete(User $user, FinancialEntry $entry): bool
    {
        return $this->update($user, $entry);
    }

    private function sharesGroup(User $user, FinancialEntry $entry): bool
    {
        $groupId = $this->groupIdOf($entry);

        return $groupId !== null && $user->groups()->whereKey($groupId)->exists();
    }

    private function groupIdOf(FinancialEntry $entry): ?int
    {
        $company = Company::query()->find($entry->getAttribute('company_id'));

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
