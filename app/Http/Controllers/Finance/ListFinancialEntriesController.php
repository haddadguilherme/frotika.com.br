<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Enums\FinancialEntryStatus;
use App\Domain\Finance\Enums\FinancialEntryType;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Fleet\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ListFinancialEntriesController
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', FinancialEntry::class);

        $filters = [
            'status' => $this->validEnum($request->string('status')->toString(), ['forecast', 'settled', 'canceled']),
            'type' => $this->validEnum($request->string('type')->toString(), ['revenue', 'expense']),
            'category' => $request->integer('category') ?: null,
            'vehicle' => $request->integer('vehicle') ?: null,
            'account' => $request->integer('account') ?: null,
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
            'q' => trim($request->string('q')->toString()),
        ];

        $entries = $this->baseQuery($filters)
            ->with(['category:id,code,name', 'bankAccount:id,name'])
            ->orderByDesc('competence_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $totals = $this->totals($filters);

        return view('financial-entries.index', [
            'entries' => $entries,
            'filters' => $filters,
            'totals' => $totals,
            'categories' => FinancialCategory::query()
                ->whereNotNull('type')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type']),
            'vehicles' => Vehicle::query()->orderBy('plate')->get(['id', 'plate']),
            'accounts' => BankAccount::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'canManage' => Gate::allows('create', FinancialEntry::class),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<FinancialEntry>
     */
    private function baseQuery(array $filters): Builder
    {
        return FinancialEntry::query()
            ->when($filters['status'] !== null, fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['type'] !== null, fn (Builder $q) => $q->where('type', $filters['type']))
            ->when($filters['category'] !== null, fn (Builder $q) => $q->where('financial_category_id', $filters['category']))
            ->when($filters['vehicle'] !== null, fn (Builder $q) => $q->where('vehicle_id', $filters['vehicle']))
            ->when($filters['account'] !== null, fn (Builder $q) => $q->where('bank_account_id', $filters['account']))
            ->when($filters['from'] !== null, fn (Builder $q) => $q->whereDate('competence_date', '>=', $filters['from']))
            ->when($filters['to'] !== null, fn (Builder $q) => $q->whereDate('competence_date', '<=', $filters['to']))
            ->when($filters['q'] !== '', fn (Builder $q) => $q->where('description', 'like', '%'.$filters['q'].'%'));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{revenue_cents: int, expense_cents: int, net_cents: int}
     */
    private function totals(array $filters): array
    {
        $query = $this->baseQuery($filters)->where('status', '!=', FinancialEntryStatus::Canceled->value);

        $revenue = (int) (clone $query)->where('type', FinancialEntryType::Revenue->value)->sum('amount_cents');
        $expense = (int) (clone $query)->where('type', FinancialEntryType::Expense->value)->sum('amount_cents');

        return [
            'revenue_cents' => $revenue,
            'expense_cents' => $expense,
            'net_cents' => $revenue - $expense,
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    private function validEnum(string $value, array $allowed): ?string
    {
        return in_array($value, $allowed, true) ? $value : null;
    }
}
