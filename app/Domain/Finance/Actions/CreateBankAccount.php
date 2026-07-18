<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Data\BankAccountData;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

final class CreateBankAccount
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly RecalculateBankAccountCurrentBalance $recalculateBankAccountCurrentBalance,
    ) {}

    public function execute(User $actor, Company $company, BankAccountData $data): BankAccount
    {
        Gate::forUser($actor)->authorize('create', BankAccount::class);

        $account = $this->tenant->runFor($company, function () use ($data): BankAccount {
            if ($data->isDefault) {
                // Só uma conta padrão por empresa (índice único virtual `default_flag`).
                BankAccount::query()->where('is_default', true)->update(['is_default' => false]);
            }

            /** @var BankAccount $account */
            $account = BankAccount::query()->create($data->toAttributes());

            return $account;
        });

        $this->recalculateBankAccountCurrentBalance->execute($company, (int) $account->getKey());

        return $account;
    }
}
