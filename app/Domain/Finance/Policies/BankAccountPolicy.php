<?php

declare(strict_types=1);

namespace App\Domain\Finance\Policies;

use App\Domain\Finance\Models\BankAccount;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;

final class BankAccountPolicy
{
    /**
     * @var list<string>
     */
    private const MANAGER_ROLES = ['owner', 'admin'];

    public function viewAny(User $user): bool
    {
        return $user->current_group_id !== null;
    }

    public function view(User $user, BankAccount $account): bool
    {
        return $this->sharesGroup($user, $account);
    }

    public function create(User $user): bool
    {
        return $user->current_group_id !== null
            && $this->manages($user, (int) $user->current_group_id);
    }

    public function update(User $user, BankAccount $account): bool
    {
        $groupId = $this->groupIdOf($account);

        return $groupId !== null && $this->manages($user, $groupId);
    }

    public function delete(User $user, BankAccount $account): bool
    {
        return $this->update($user, $account);
    }

    private function sharesGroup(User $user, BankAccount $account): bool
    {
        $groupId = $this->groupIdOf($account);

        return $groupId !== null && $user->groups()->whereKey($groupId)->exists();
    }

    private function groupIdOf(BankAccount $account): ?int
    {
        $company = Company::query()->find($account->getAttribute('company_id'));

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
