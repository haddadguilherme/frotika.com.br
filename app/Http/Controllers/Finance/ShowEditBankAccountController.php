<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Models\BankAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ShowEditBankAccountController
{
    public function __invoke(Request $request, int $account): View
    {
        $model = BankAccount::query()->findOrFail($account);

        Gate::authorize('update', $model);

        return view('bank-accounts.edit', [
            'account' => $model,
        ]);
    }
}
