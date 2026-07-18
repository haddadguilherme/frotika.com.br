<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Actions\DeactivateBankAccount;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DeactivateBankAccountController
{
    public function __invoke(Request $request, int $account, DeactivateBankAccount $action): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $model = BankAccount::query()->findOrFail($account);
        $company = Company::query()->findOrFail($model->getAttribute('company_id'));

        $action->execute($user, $company, $model);

        return redirect()
            ->route('bank-accounts.index')
            ->with('status', 'Conta bancária removida.');
    }
}
