<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Data\BankAccountData;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

final class UpdateBankAccount
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly RecalculateBankAccountCurrentBalance $recalculateBankAccountCurrentBalance,
    ) {}

    public function execute(User $actor, Company $company, BankAccount $account, BankAccountData $data): BankAccount
    {
        Gate::forUser($actor)->authorize('update', $account);

        $updated = $this->tenant->runFor($company, function () use ($account, $data): BankAccount {
            if ($data->isDefault) {
                BankAccount::query()
                    ->where('is_default', true)
                    ->whereKeyNot($account->getKey())
                    ->update(['is_default' => false]);
            }

            $account->fill($data->toAttributes());
            $account->save();

            return $account;
        });

        $this->recalculateBankAccountCurrentBalance->execute($company, (int) $updated->getKey());

        return $updated;
    }
}
