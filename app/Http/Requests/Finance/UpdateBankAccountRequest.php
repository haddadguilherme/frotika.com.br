<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Domain\Finance\Models\BankAccount;
use Illuminate\Support\Facades\Gate;

final class UpdateBankAccountRequest extends BankAccountRequest
{
    public function authorize(): bool
    {
        $account = BankAccount::query()->find($this->route('account'));

        return $account instanceof BankAccount && Gate::allows('update', $account);
    }
}
