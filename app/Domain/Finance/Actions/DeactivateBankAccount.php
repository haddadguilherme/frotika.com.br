<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class DeactivateBankAccount
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function execute(User $actor, Company $company, BankAccount $account): void
    {
        Gate::forUser($actor)->authorize('delete', $account);

        $this->tenant->runFor($company, function () use ($account): void {
            $hasLinkedEntries = FinancialEntry::query()
                ->where('bank_account_id', $account->getKey())
                ->exists();

            if ($hasLinkedEntries) {
                throw ValidationException::withMessages([
                    'bank_account' => 'Conta com lançamentos vinculados não pode ser removida. Desative os lançamentos ou transfira antes.',
                ]);
            }

            $account->forceFill([
                'is_default' => false,
                'active' => false,
            ])->save();

            $account->delete();
        });
    }
}
