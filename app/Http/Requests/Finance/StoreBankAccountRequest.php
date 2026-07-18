<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Domain\Finance\Models\BankAccount;
use Illuminate\Support\Facades\Gate;

final class StoreBankAccountRequest extends BankAccountRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', BankAccount::class);
    }
}
