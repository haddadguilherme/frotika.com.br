<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Actions\UpdateBankAccount;
use App\Domain\Finance\Data\BankAccountData;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Tenancy\Models\Company;
use App\Http\Requests\Finance\UpdateBankAccountRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class UpdateBankAccountController
{
    public function __invoke(UpdateBankAccountRequest $request, int $account, UpdateBankAccount $action): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $model = BankAccount::query()->findOrFail($account);
        $company = Company::query()->findOrFail($model->getAttribute('company_id'));

        $action->execute($user, $company, $model, BankAccountData::fromArray($request->validated()));

        return redirect()
            ->route('bank-accounts.index')
            ->with('status', 'Conta bancária atualizada com sucesso.');
    }
}
