<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Domain\Finance\Enums\FinancialCategoryAllocation;
use App\Domain\Finance\Enums\FinancialEntryStatus;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Reports\Dre\DreBuilder;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class ShowDreController
{
    /**
     * Chaves de ordenação do comparativo → como ler o valor de cada veículo.
     *
     * @var array<string, callable(array<string, mixed>): (int|float|string)>
     */
    private array $sorters;

    public function __construct()
    {
        $this->sorters = [
            'plate' => static fn (array $v): string => (string) $v['plate'],
            'km' => static fn (array $v): int => (int) $v['km'],
            'revenue_km' => static fn (array $v): float => (float) $v['per_km']['revenue'],
            'cost_km' => static fn (array $v): float => (float) $v['per_km']['cost'],
            'consumption' => static fn (array $v): float => (float) ($v['consumption'] ?? 0.0),
            'net_result' => static fn (array $v): int => (int) $v['metrics']['net_result_cents'],
            'economic_result' => static fn (array $v): int => (int) $v['economic_result_cents'],
        ];
    }

    public function __invoke(Request $request, DreBuilder $builder): View|RedirectResponse
    {
        Gate::authorize('viewAny', FinancialEntry::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $company = Company::query()->find($user->current_company_id);

        if (! $company instanceof Company) {
            return redirect()
                ->route('companies.index')
                ->with('warning', 'Selecione uma empresa ativa para ver o DRE.');
        }

        $today = CarbonImmutable::now();
        $from = $request->date('from') ?: $today->startOfMonth();
        $to = $request->date('to') ?: $today->endOfMonth();
        $fromDate = CarbonImmutable::parse($from->format('Y-m-d'));
        $toDate = CarbonImmutable::parse($to->format('Y-m-d'));

        if ($toDate->lt($fromDate)) {
            $toDate = $fromDate;
        }

        $vehicleId = $request->integer('vehicle') ?: null;

        $filters = [
            'from' => $fromDate->format('Y-m-d'),
            'to' => $toDate->format('Y-m-d'),
            'vehicle' => $vehicleId,
        ];

        $vehicles = Vehicle::query()->orderBy('plate')->get(['id', 'plate', 'type']);

        if ($vehicleId !== null) {
            return $this->individual($builder, $company, $filters, $vehicleId, $vehicles);
        }

        return $this->comparative($request, $builder, $company, $filters, $vehicles);
    }

    /**
     * @param  array{from: string, to: string, vehicle: int|null}  $filters
     * @param  Collection<int, Vehicle>  $vehicles
     */
    private function comparative(
        Request $request,
        DreBuilder $builder,
        Company $company,
        array $filters,
        $vehicles,
    ): View {
        $dre = $builder->execute($company, $filters['from'], $filters['to']);

        [$sortKey, $sortDir] = $this->resolveSort($request->string('sort')->toString());

        $rows = $dre['vehicles'];
        $sorter = $this->sorters[$sortKey];

        usort($rows, static function (array $a, array $b) use ($sorter, $sortDir): int {
            $result = $sorter($a) <=> $sorter($b);

            return $sortDir === 'desc' ? -$result : $result;
        });

        return view('dre.index', [
            'mode' => 'comparative',
            'dre' => $dre,
            'rows' => $rows,
            'filters' => $filters,
            'sort' => ['key' => $sortKey, 'dir' => $sortDir],
            'vehicles' => $vehicles,
        ]);
    }

    /**
     * @param  array{from: string, to: string, vehicle: int|null}  $filters
     * @param  Collection<int, Vehicle>  $vehicles
     */
    private function individual(
        DreBuilder $builder,
        Company $company,
        array $filters,
        int $vehicleId,
        $vehicles,
    ): View {
        $dre = $builder->execute($company, $filters['from'], $filters['to'], [$vehicleId]);

        if ($dre['vehicles'] === []) {
            abort(404);
        }

        $vehicle = $dre['vehicles'][0];

        return view('dre.index', [
            'mode' => 'individual',
            'dre' => $dre,
            'vehicle' => $vehicle,
            'entriesByCategory' => $this->entriesByCategory($filters, $vehicleId),
            'filters' => $filters,
            'vehicles' => $vehicles,
        ]);
    }

    /**
     * Lançamentos diretos do veículo agrupados por categoria — o drill-down de
     * cada linha do DRE. Só categorias diretas (as rateadas são linha única).
     *
     * @param  array{from: string, to: string, vehicle: int|null}  $filters
     * @return array<int, list<array{id: int, description: string, competence_date: string, amount_cents: int}>>
     */
    private function entriesByCategory(array $filters, int $vehicleId): array
    {
        $entries = FinancialEntry::query()
            ->with('category:id,dre_group,allocation,affects_cashflow')
            ->where('vehicle_id', $vehicleId)
            ->whereBetween('competence_date', [$filters['from'], $filters['to']])
            ->where('status', '<>', FinancialEntryStatus::Canceled->value)
            ->orderByDesc('competence_date')
            ->orderByDesc('id')
            ->get();

        $result = [];

        foreach ($entries as $entry) {
            $category = $entry->category;

            if ($category === null
                || $category->getAttribute('affects_cashflow') !== true
                || $category->allocation !== FinancialCategoryAllocation::VehicleDirect
                || $category->dre_group === null) {
                continue;
            }

            $categoryId = (int) $entry->getAttribute('financial_category_id');
            $amountCents = (int) $entry->getAttribute('amount_cents');
            $signed = $entry->type->value === 'expense' ? -$amountCents : $amountCents;

            $result[$categoryId][] = [
                'id' => (int) $entry->getKey(),
                'description' => (string) $entry->getAttribute('description'),
                'competence_date' => CarbonImmutable::parse($entry->getAttribute('competence_date'))->toDateString(),
                'amount_cents' => $signed,
            ];
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveSort(string $sort): array
    {
        $dir = 'asc';
        $key = $sort;

        if (str_starts_with($sort, '-')) {
            $dir = 'desc';
            $key = substr($sort, 1);
        }

        if (! array_key_exists($key, $this->sorters)) {
            // Padrão: pior resultado no topo — é o momento "aha" do produto.
            return ['net_result', 'asc'];
        }

        return [$key, $dir];
    }
}
