<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Models\BankAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ShowCreateBankAccountController
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('create', BankAccount::class);

        return view('bank-accounts.create');
    }
}
