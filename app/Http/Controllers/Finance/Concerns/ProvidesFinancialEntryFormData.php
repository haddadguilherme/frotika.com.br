<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance\Concerns;

use App\Domain\Finance\Enums\FinancialEntryPaymentMethod;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Fleet\Models\Vehicle;

trait ProvidesFinancialEntryFormData
{
    /**
     * @return array<string, mixed>
     */
    protected function formData(): array
    {
        return [
            'categories' => FinancialCategory::query()
                ->whereNotNull('type')
                ->where('active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type']),
            'accounts' => BankAccount::query()
                ->where('active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name']),
            'vehicles' => Vehicle::query()->orderBy('plate')->get(['id', 'plate']),
            'paymentMethods' => FinancialEntryPaymentMethod::cases(),
        ];
    }
}
