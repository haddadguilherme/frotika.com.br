<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Models\BankAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ListBankAccountsController
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', BankAccount::class);

        $accounts = BankAccount::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'bank_code', 'agency', 'number', 'current_balance_cents', 'is_default', 'active']);

        return view('bank-accounts.index', [
            'accounts' => $accounts,
            'canManage' => Gate::allows('create', BankAccount::class),
        ]);
    }
}
