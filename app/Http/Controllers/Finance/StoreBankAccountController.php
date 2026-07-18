<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Actions\CreateBankAccount;
use App\Domain\Finance\Data\BankAccountData;
use App\Domain\Tenancy\Models\Company;
use App\Http\Requests\Finance\StoreBankAccountRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class StoreBankAccountController
{
    public function __invoke(StoreBankAccountRequest $request, CreateBankAccount $action): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $company = Company::query()->find($user->current_company_id);

        if (! $company instanceof Company) {
            return redirect()
                ->route('companies.index')
                ->with('warning', 'Selecione uma empresa ativa antes de cadastrar contas.');
        }

        $action->execute($user, $company, BankAccountData::fromArray($request->validated()));

        return redirect()
            ->route('bank-accounts.index')
            ->with('status', 'Conta bancária cadastrada com sucesso.');
    }
}
