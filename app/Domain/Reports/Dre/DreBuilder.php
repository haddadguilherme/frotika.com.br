<?php

declare(strict_types=1);

namespace App\Domain\Reports\Dre;

use App\Domain\Finance\Enums\FinancialCategoryAllocation;
use App\Domain\Finance\Enums\FinancialCategoryDreGroup;
use App\Domain\Finance\Enums\FinancialEntryStatus;
use App\Domain\Finance\Enums\FinancialEntryType;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Enums\VehicleType;
use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Fleet\Models\VehicleCostParameter;
use App\Domain\Fleet\Models\VehicleOdometerReading;
use App\Domain\Fuelings\Models\Fueling;
use App\Domain\Maintenances\Models\Maintenance;
use App\Domain\Reports\Reserves\ReserveParameters;
use App\Domain\Reports\Reserves\VehicleReservesCalculator;
use App\Domain\Tenancy\Models\Company;
use App\Support\Money\Apportionment;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;

final class DreBuilder
{
    private const METHOD_BY_KM = 'by_km';

    private const METHOD_BY_REVENUE = 'by_revenue';

    private const METHOD_EQUAL = 'equal';

    private const METHOD_NONE = 'none';

    /**
     * @var list<string>
     */
    private const VALID_METHODS = [
        self::METHOD_BY_KM,
        self::METHOD_BY_REVENUE,
        self::METHOD_EQUAL,
        self::METHOD_NONE,
    ];

    /**
     * @var list<string>
     */
    private const ELIGIBLE_VEHICLE_TYPES = [
        VehicleType::Tractor->value,
        VehicleType::Truck->value,
        VehicleType::Toco->value,
        VehicleType::Vuc->value,
    ];

    /**
     * @var list<string>
     */
    private const INELIGIBLE_VEHICLE_STATUSES = [
        VehicleStatus::Inactive->value,
        VehicleStatus::Sold->value,
    ];

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly VehicleReservesCalculator $reserves,
    ) {}

    /**
     * @param  list<int>|null  $vehicleIds
     * @return array{
     *     from: string,
     *     to: string,
     *     apportionment: array{
     *         method: string,
     *         basis_total: int,
     *         divisor_zero: bool,
     *         warnings: list<string>
     *     },
     *     totals: array{
     *         vehicles_count: int,
     *         groups_cents: array<string, int>,
     *         gross_revenue_cents: int,
     *         deductions_cents: int,
     *         net_revenue_cents: int,
     *         variable_cost_cents: int,
     *         contribution_margin_cents: int,
     *         fixed_cost_cents: int,
     *         operational_result_cents: int,
     *         admin_expense_cents: int,
     *         financial_expense_cents: int,
     *         net_result_cents: int,
     *         km: int,
     *         liters: float,
     *         consumption: float|null,
     *         per_km: array{revenue: float, cost: float, breakeven: float|null},
     *         breakeven_km: int|null,
     *         reserves: array{oil_cents: int, tire_cents: int, prudential_cents: int, driver_salary_cents: int, prolabore_cents: int, total_cents: int},
     *         result_before_reserves_cents: int,
     *         economic_result_cents: int
     *     },
     *     vehicles: list<array{
     *         vehicle_id: int,
     *         plate: string,
     *         type: string|null,
     *         status: string|null,
     *         groups_cents: array<string, int>,
     *         metrics: array{
     *             gross_revenue_cents: int,
     *             deductions_cents: int,
     *             net_revenue_cents: int,
     *             variable_cost_cents: int,
     *             contribution_margin_cents: int,
     *             fixed_cost_cents: int,
     *             operational_result_cents: int,
     *             admin_expense_cents: int,
     *             financial_expense_cents: int,
     *             net_result_cents: int
     *         },
     *         km: int,
     *         consumption_km: int,
     *         liters: float,
     *         consumption: float|null,
     *         per_km: array{revenue: float, cost: float, breakeven: float|null},
     *         breakeven_km: int|null,
     *         reserves: array{
     *             oil_cents: int,
     *             tire_cents: int,
     *             prudential_cents: int,
     *             driver_salary_cents: int,
     *             prolabore_cents: int,
     *             total_cents: int,
     *             params: array{oil_reserve_per_km: float, tire_reserve_per_km: float, prudential_reserve_per_km: float, driver_salary_cents: int, prolabore_percent: float, months: float}
     *         },
     *         result_before_reserves_cents: int,
     *         economic_result_cents: int,
     *         categories: list<array{category_id: int, code: string, name: string, dre_group: string, amount_cents: int}>,
     *         apportionment: array{
     *             basis_value: int,
     *             basis_percent: float
     *         }
     *     }>
     * }
     */
    public function execute(
        Company $company,
        string $fromDate,
        string $toDate,
        ?array $vehicleIds = null,
    ): array {
        $from = CarbonImmutable::parse($fromDate)->startOfDay();
        $to = CarbonImmutable::parse($toDate)->startOfDay();

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'to_date' => 'A data final deve ser maior ou igual a data inicial.',
            ]);
        }

        $resolvedVehicleIds = $this->normalizeIntegerList($vehicleIds, 'vehicle_ids');

        return $this->tenant->runFor($company, function () use ($company, $from, $to, $resolvedVehicleIds): array {
            $directByVehicleGroup = $this->loadDirectByVehicleGroup($from, $to);
            $apportionedByGroup = $this->loadApportionedByGroup($from, $to);
            $eligibleVehicles = $this->loadEligibleVehiclesForApportionment();
            $method = $this->resolveApportionmentMethod($company);
            $weightsByVehicle = $this->resolveWeightsByVehicle($method, $eligibleVehicles, $directByVehicleGroup, $from, $to);
            $apportionedByVehicleGroup = $this->apportionByVehicleGroup($apportionedByGroup, $weightsByVehicle);

            $vehicleIdsForOutput = $this->resolveVehicleIdsForOutput(
                $resolvedVehicleIds,
                $directByVehicleGroup,
                $weightsByVehicle,
            );

            $vehicles = $this->loadVehiclesForOutput($vehicleIdsForOutput);
            $kmByVehicle = $this->loadKmByVehicle($vehicleIdsForOutput, $from, $to);
            $distanceByVehicle = $this->loadDistanceByVehicle($vehicleIdsForOutput, $from, $to);
            $directCategoriesByVehicle = $this->loadDirectCategoriesByVehicle($from, $to);
            $reserveParamsByVehicle = $this->loadReserveParameters($vehicleIdsForOutput);
            $months = $this->monthsInPeriod($from, $to);
            $basisTotal = array_sum($weightsByVehicle);
            $warnings = [];

            if ($method !== self::METHOD_NONE && $basisTotal === 0 && $apportionedByGroup !== []) {
                $warnings[] = 'Sem base de rateio no período; despesas rateadas ficaram zeradas.';
            }

            $vehiclesPayload = [];

            foreach ($vehicleIdsForOutput as $vehicleId) {
                $groups = $this->emptyGroups();

                foreach (($directByVehicleGroup[$vehicleId] ?? []) as $group => $value) {
                    $groups[$group] += $value;
                }

                foreach (($apportionedByVehicleGroup[$vehicleId] ?? []) as $group => $value) {
                    $groups[$group] += $value;
                }

                $metrics = $this->buildMetrics($groups);
                $vehicle = $vehicles->get($vehicleId);
                $basisValue = $weightsByVehicle[$vehicleId] ?? 0;
                $basisPercent = $basisTotal > 0
                    ? round(($basisValue * 100) / $basisTotal, 2)
                    : 0.0;

                // Distância (hodômetro) alimenta reservas, R$/km e o card de km;
                // consumo (tanque cheio, regra 8) alimenta só o km/l.
                $distance = $distanceByVehicle[$vehicleId] ?? 0;
                $consumptionKm = $kmByVehicle[$vehicleId]['km'] ?? 0;
                $liters = $kmByVehicle[$vehicleId]['liters'] ?? 0.0;

                $params = $reserveParamsByVehicle[$vehicleId] ?? new ReserveParameters;
                $reserves = $this->reserves->calculate($params, $distance, $months, $metrics['net_revenue_cents']);
                $resultBeforeReserves = $metrics['net_result_cents'] + $reserves['driver_salary_cents'] + $reserves['prolabore_cents'];

                $vehiclesPayload[] = [
                    'vehicle_id' => $vehicleId,
                    'plate' => $vehicle?->getAttribute('plate') ?? ('#'.$vehicleId),
                    'type' => $vehicle?->getAttribute('type')?->value,
                    'status' => $vehicle?->getAttribute('status')?->value,
                    'groups_cents' => $groups,
                    'metrics' => $metrics,
                    'km' => $distance,
                    'consumption_km' => $consumptionKm,
                    'liters' => $liters,
                    'consumption' => $liters > 0.0 ? round($consumptionKm / $liters, 2) : null,
                    'per_km' => $this->buildPerKm($metrics, $distance),
                    'breakeven_km' => $this->buildBreakevenKm($metrics, $distance),
                    'reserves' => [
                        ...$reserves,
                        'params' => [
                            'oil_reserve_per_km' => $params->oilReservePerKm,
                            'tire_reserve_per_km' => $params->tireReservePerKm,
                            'prudential_reserve_per_km' => $params->prudentialReservePerKm,
                            'driver_salary_cents' => $params->driverSalaryCents,
                            'prolabore_percent' => $params->prolaborePercent,
                            'months' => $months,
                        ],
                    ],
                    'result_before_reserves_cents' => $resultBeforeReserves,
                    'economic_result_cents' => $metrics['net_result_cents'] + $reserves['total_cents'],
                    'categories' => $directCategoriesByVehicle[$vehicleId] ?? [],
                    'apportionment' => [
                        'basis_value' => $basisValue,
                        'basis_percent' => $basisPercent,
                    ],
                ];
            }

            return [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'apportionment' => [
                    'method' => $method,
                    'basis_total' => $basisTotal,
                    'divisor_zero' => $method !== self::METHOD_NONE && $basisTotal === 0,
                    'warnings' => $warnings,
                ],
                'totals' => $this->buildTotals($vehiclesPayload),
                'vehicles' => $vehiclesPayload,
            ];
        });
    }

    /**
     * @return array<int, array<string, int>>
     */
    private function loadDirectByVehicleGroup(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = FinancialEntry::query()
            ->join('financial_categories as category', 'financial_entries.financial_category_id', '=', 'category.id')
            ->selectRaw('financial_entries.vehicle_id as vehicle_id')
            ->selectRaw('category.dre_group as dre_group')
            ->selectRaw(
                'SUM(CASE WHEN financial_entries.type = ? THEN -financial_entries.amount_cents ELSE financial_entries.amount_cents END) as total_cents',
                [FinancialEntryType::Expense->value],
            )
            ->whereBetween('financial_entries.competence_date', [$from->toDateString(), $to->toDateString()])
            ->where('financial_entries.status', '<>', FinancialEntryStatus::Canceled->value)
            ->where('category.allocation', FinancialCategoryAllocation::VehicleDirect->value)
            ->where('category.affects_cashflow', true)
            ->whereNotNull('financial_entries.vehicle_id')
            ->whereNotNull('category.dre_group')
            ->groupBy('financial_entries.vehicle_id', 'category.dre_group')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $vehicleId = (int) $row->getAttribute('vehicle_id');
            $group = (string) $row->getAttribute('dre_group');

            if (! array_key_exists($group, $this->emptyGroups())) {
                continue;
            }

            $result[$vehicleId] ??= [];
            $result[$vehicleId][$group] = (int) $row->getAttribute('total_cents');
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    private function loadApportionedByGroup(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = FinancialEntry::query()
            ->join('financial_categories as category', 'financial_entries.financial_category_id', '=', 'category.id')
            ->selectRaw('category.dre_group as dre_group')
            ->selectRaw(
                'SUM(CASE WHEN financial_entries.type = ? THEN -financial_entries.amount_cents ELSE financial_entries.amount_cents END) as total_cents',
                [FinancialEntryType::Expense->value],
            )
            ->whereBetween('financial_entries.competence_date', [$from->toDateString(), $to->toDateString()])
            ->where('financial_entries.status', '<>', FinancialEntryStatus::Canceled->value)
            ->where('category.allocation', FinancialCategoryAllocation::Apportioned->value)
            ->where('category.affects_cashflow', true)
            ->whereNotNull('category.dre_group')
            ->groupBy('category.dre_group')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $group = (string) $row->getAttribute('dre_group');

            if (! array_key_exists($group, $this->emptyGroups())) {
                continue;
            }

            $result[$group] = (int) $row->getAttribute('total_cents');
        }

        return $result;
    }

    /**
     * @return EloquentCollection<int, Vehicle>
     */
    private function loadEligibleVehiclesForApportionment(): EloquentCollection
    {
        /** @var EloquentCollection<int, Vehicle> $vehicles */
        $vehicles = Vehicle::query()
            ->whereIn('type', self::ELIGIBLE_VEHICLE_TYPES)
            ->whereNotIn('status', self::INELIGIBLE_VEHICLE_STATUSES)
            ->orderBy('plate')
            ->get(['id', 'plate', 'type', 'status']);

        return $vehicles;
    }

    /**
     * Km e litros dos intervalos de tanque cheio fechados (regra 8), usados
     * **apenas para o consumo (km/l)**: `km_since_last` e `km_per_liter` só
     * existem quando o intervalo fechou; Arla 32 e óleo nunca geram esses
     * campos. A distância total do período vem do hodômetro (loadDistanceByVehicle).
     *
     * @param  list<int>  $vehicleIds
     * @return array<int, array{km: int, liters: float}>
     */
    private function loadKmByVehicle(array $vehicleIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        $rows = Fueling::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereBetween('fueled_at', [$from->startOfDay(), $to->endOfDay()])
            ->whereNotNull('km_since_last')
            ->where('km_since_last', '>', 0)
            ->whereNotNull('km_per_liter')
            ->where('km_per_liter', '>', 0)
            ->get(['vehicle_id', 'km_since_last', 'km_per_liter']);

        $result = [];

        foreach ($rows as $row) {
            $vehicleId = (int) $row->getAttribute('vehicle_id');
            $km = (int) $row->getAttribute('km_since_last');
            $kmPerLiter = (float) $row->getAttribute('km_per_liter');

            $result[$vehicleId] ??= ['km' => 0, 'liters' => 0.0];
            $result[$vehicleId]['km'] += $km;
            $result[$vehicleId]['liters'] += $km / $kmPerLiter;
        }

        return $result;
    }

    /**
     * Distância percorrida no período por hodômetro (híbrido). Reúne snapshots
     * (data, odômetro) de abastecimentos, manutenções e leituras manuais, e
     * calcula km = (última leitura ≤ to) − (última leitura < from). Sem leitura
     * anterior ao período, usa a menor leitura dentro dele (subestima). Menos de
     * duas leituras → veículo ausente do mapa (km desconhecido, não zero).
     *
     * @param  list<int>  $vehicleIds
     * @return array<int, int>
     */
    private function loadDistanceByVehicle(array $vehicleIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        /** @var array<int, list<array{date: string, odometer: int}>> $snapshots */
        $snapshots = [];

        $push = function (int $vehicleId, ?string $date, mixed $odometer) use (&$snapshots): void {
            if ($date === null) {
                return;
            }

            $odometer = (int) $odometer;

            if ($odometer <= 0) {
                return;
            }

            $snapshots[$vehicleId][] = ['date' => $date, 'odometer' => $odometer];
        };

        $toDate = $to->toDateString();
        $fromDate = $from->toDateString();

        $fuelings = Fueling::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->where('fueled_at', '<=', $to->endOfDay())
            ->get(['vehicle_id', 'fueled_at', 'odometer']);

        foreach ($fuelings as $row) {
            $push(
                (int) $row->getAttribute('vehicle_id'),
                $row->getAttribute('fueled_at')?->toDateString(),
                $row->getAttribute('odometer'),
            );
        }

        $maintenances = Maintenance::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereNotNull('odometer')
            ->where('opened_at', '<=', $toDate)
            ->get(['vehicle_id', 'opened_at', 'odometer']);

        foreach ($maintenances as $row) {
            $push(
                (int) $row->getAttribute('vehicle_id'),
                $row->getAttribute('opened_at')?->toDateString(),
                $row->getAttribute('odometer'),
            );
        }

        $readings = VehicleOdometerReading::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->where('read_on', '<=', $toDate)
            ->get(['vehicle_id', 'read_on', 'odometer']);

        foreach ($readings as $row) {
            $push(
                (int) $row->getAttribute('vehicle_id'),
                $row->getAttribute('read_on')?->toDateString(),
                $row->getAttribute('odometer'),
            );
        }

        $result = [];

        foreach ($snapshots as $vehicleId => $items) {
            if (count($items) < 2) {
                continue;
            }

            $end = null;
            $beforeFrom = null;
            $minWithin = null;

            foreach ($items as $item) {
                $date = $item['date'];
                $odometer = $item['odometer'];

                if ($date <= $toDate) {
                    $end = $end === null ? $odometer : max($end, $odometer);
                }

                if ($date < $fromDate) {
                    $beforeFrom = $beforeFrom === null ? $odometer : max($beforeFrom, $odometer);
                }

                if ($date >= $fromDate && $date <= $toDate) {
                    $minWithin = $minWithin === null ? $odometer : min($minWithin, $odometer);
                }
            }

            $start = $beforeFrom ?? $minWithin;

            if ($end === null || $start === null) {
                continue;
            }

            $result[$vehicleId] = max(0, $end - $start);
        }

        return $result;
    }

    /**
     * Detalhe por categoria direta (linhas do DRE: Combustível, Arla 32, ...).
     * Só categorias `vehicle_direct`; as rateadas aparecem como linha única do
     * grupo, não itemizadas (blueprint 9.1).
     *
     * @return array<int, list<array{category_id: int, code: string, name: string, dre_group: string, amount_cents: int}>>
     */
    private function loadDirectCategoriesByVehicle(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = FinancialEntry::query()
            ->join('financial_categories as category', 'financial_entries.financial_category_id', '=', 'category.id')
            ->selectRaw('financial_entries.vehicle_id as vehicle_id')
            ->selectRaw('category.id as category_id')
            ->selectRaw('category.code as code')
            ->selectRaw('category.name as name')
            ->selectRaw('category.dre_group as dre_group')
            ->selectRaw('category.sort_order as sort_order')
            ->selectRaw(
                'SUM(CASE WHEN financial_entries.type = ? THEN -financial_entries.amount_cents ELSE financial_entries.amount_cents END) as total_cents',
                [FinancialEntryType::Expense->value],
            )
            ->whereBetween('financial_entries.competence_date', [$from->toDateString(), $to->toDateString()])
            ->where('financial_entries.status', '<>', FinancialEntryStatus::Canceled->value)
            ->where('category.allocation', FinancialCategoryAllocation::VehicleDirect->value)
            ->where('category.affects_cashflow', true)
            ->whereNotNull('financial_entries.vehicle_id')
            ->whereNotNull('category.dre_group')
            ->groupBy('financial_entries.vehicle_id', 'category.id', 'category.code', 'category.name', 'category.dre_group', 'category.sort_order')
            ->orderBy('category.sort_order')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $vehicleId = (int) $row->getAttribute('vehicle_id');
            $group = (string) $row->getAttribute('dre_group');

            if (! array_key_exists($group, $this->emptyGroups())) {
                continue;
            }

            $result[$vehicleId][] = [
                'category_id' => (int) $row->getAttribute('category_id'),
                'code' => (string) $row->getAttribute('code'),
                'name' => (string) $row->getAttribute('name'),
                'dre_group' => $group,
                'amount_cents' => (int) $row->getAttribute('total_cents'),
            ];
        }

        return $result;
    }

    /**
     * Parâmetros de reserva por veículo, já resolvidos contra o padrão da
     * empresa (linha com vehicle_id nulo). Um veículo sem override herda o
     * padrão; sem padrão, tudo zero.
     *
     * @param  list<int>  $vehicleIds
     * @return array<int, ReserveParameters>
     */
    private function loadReserveParameters(array $vehicleIds): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        /** @var VehicleCostParameter|null $default */
        $default = VehicleCostParameter::query()->whereNull('vehicle_id')->first();

        /** @var EloquentCollection<int, VehicleCostParameter> $rows */
        $rows = VehicleCostParameter::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->get()
            ->keyBy('vehicle_id');

        $result = [];

        foreach ($vehicleIds as $vehicleId) {
            $result[$vehicleId] = ReserveParameters::resolve($rows->get($vehicleId), $default);
        }

        return $result;
    }

    /**
     * Meses (decimais) contidos no período, para prorratear provisões mensais
     * (salário, pró-labore). Cada mês do calendário contribui com a fração de
     * dias coberta: julho inteiro = 1,0; 1 a 15 de julho = 15/31. Assim um
     * período de dois meses e meio vale 2,5 salários, sem inventar "mês médio".
     */
    private function monthsInPeriod(CarbonImmutable $from, CarbonImmutable $to): float
    {
        $months = 0.0;
        $cursor = $from->startOfMonth();
        $lastMonth = $to->startOfMonth();

        while ($cursor->lte($lastMonth)) {
            $monthStart = $cursor;
            $monthEnd = $cursor->endOfMonth();

            $periodStart = $from->gt($monthStart) ? $from : $monthStart;
            $periodEnd = $to->lt($monthEnd) ? $to : $monthEnd;

            $daysCovered = (int) $periodStart->startOfDay()->diffInDays($periodEnd->startOfDay()) + 1;
            $months += $daysCovered / $monthStart->daysInMonth;

            $cursor = $cursor->addMonth();
        }

        return round($months, 6);
    }

    /**
     * Receita, custo e equilíbrio por km em reais (taxa, não valor monetário
     * armazenado — casa com o componente da régua). Custo inclui rateio; o
     * equilíbrio mostra só o custo direto (variável + fixo do veículo).
     *
     * @param  array<string, int>  $metrics
     * @return array{revenue: float, cost: float, breakeven: float|null}
     */
    private function buildPerKm(array $metrics, int $km): array
    {
        if ($km <= 0) {
            return ['revenue' => 0.0, 'cost' => 0.0, 'breakeven' => null];
        }

        $totalCostCents = -($metrics['variable_cost_cents'] + $metrics['fixed_cost_cents'] + $metrics['admin_expense_cents'] + $metrics['financial_expense_cents']);
        $directCostCents = -($metrics['variable_cost_cents'] + $metrics['fixed_cost_cents']);

        return [
            'revenue' => round(($metrics['net_revenue_cents'] / 100) / $km, 4),
            'cost' => round(($totalCostCents / 100) / $km, 4),
            'breakeven' => round(($directCostCents / 100) / $km, 4),
        ];
    }

    /**
     * Ponto de equilíbrio em km: custos fixos ÷ margem de contribuição por km
     * (blueprint 9.1). Null quando não há km ou a margem de contribuição não é
     * positiva (não há como cobrir o fixo rodando mais).
     *
     * @param  array<string, int>  $metrics
     */
    private function buildBreakevenKm(array $metrics, int $km): ?int
    {
        if ($km <= 0) {
            return null;
        }

        $contributionPerKm = $metrics['contribution_margin_cents'] / $km;

        if ($contributionPerKm <= 0) {
            return null;
        }

        $fixedTotalCents = -($metrics['fixed_cost_cents'] + $metrics['admin_expense_cents'] + $metrics['financial_expense_cents']);

        if ($fixedTotalCents <= 0) {
            return 0;
        }

        return (int) round($fixedTotalCents / $contributionPerKm);
    }

    private function resolveApportionmentMethod(Company $company): string
    {
        $settings = $company->getAttribute('settings');

        if (is_string($settings) && $settings !== '') {
            /** @var mixed $decoded */
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($settings)) {
            return self::METHOD_BY_KM;
        }

        $method = $settings['dre_apportionment_method'] ?? null;

        if (! is_string($method)) {
            return self::METHOD_BY_KM;
        }

        return in_array($method, self::VALID_METHODS, true)
            ? $method
            : self::METHOD_BY_KM;
    }

    /**
     * @param  EloquentCollection<int, Vehicle>  $eligibleVehicles
     * @param  array<int, array<string, int>>  $directByVehicleGroup
     * @return array<int, int>
     */
    private function resolveWeightsByVehicle(
        string $method,
        EloquentCollection $eligibleVehicles,
        array $directByVehicleGroup,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        $vehicleIds = $eligibleVehicles->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        if ($vehicleIds === []) {
            return [];
        }

        if ($method === self::METHOD_NONE) {
            return array_fill_keys($vehicleIds, 0);
        }

        if ($method === self::METHOD_EQUAL) {
            return array_fill_keys($vehicleIds, 1);
        }

        if ($method === self::METHOD_BY_REVENUE) {
            $weights = [];

            foreach ($vehicleIds as $vehicleId) {
                $grossRevenue = $directByVehicleGroup[$vehicleId][FinancialCategoryDreGroup::GrossRevenue->value] ?? 0;
                $weights[$vehicleId] = max($grossRevenue, 0);
            }

            return $weights;
        }

        // Rateio por km usa a distância por hodômetro — a mesma base do R$/km
        // e das reservas, para o veículo não ser rateado por dois "km" diferentes.
        $distanceByVehicle = $this->loadDistanceByVehicle($vehicleIds, $from, $to);

        $weights = [];

        foreach ($vehicleIds as $vehicleId) {
            $weights[$vehicleId] = max($distanceByVehicle[$vehicleId] ?? 0, 0);
        }

        return $weights;
    }

    /**
     * @param  array<string, int>  $apportionedByGroup
     * @param  array<int, int>  $weightsByVehicle
     * @return array<int, array<string, int>>
     */
    private function apportionByVehicleGroup(array $apportionedByGroup, array $weightsByVehicle): array
    {
        if ($weightsByVehicle === [] || $apportionedByGroup === []) {
            return [];
        }

        $vehicleIds = array_keys($weightsByVehicle);
        $weights = array_values($weightsByVehicle);
        $result = [];

        foreach ($apportionedByGroup as $group => $totalCents) {
            $parts = Apportionment::distribute($totalCents, $weights);

            foreach ($parts as $index => $part) {
                $vehicleId = $vehicleIds[$index];
                $result[$vehicleId] ??= [];
                $result[$vehicleId][$group] = ($result[$vehicleId][$group] ?? 0) + $part;
            }
        }

        return $result;
    }

    /**
     * @param  list<int>|null  $requestedVehicleIds
     * @param  array<int, array<string, int>>  $directByVehicleGroup
     * @param  array<int, int>  $weightsByVehicle
     * @return list<int>
     */
    private function resolveVehicleIdsForOutput(?array $requestedVehicleIds, array $directByVehicleGroup, array $weightsByVehicle): array
    {
        $candidateIds = $requestedVehicleIds
            ?? array_values(array_unique([
                ...array_keys($directByVehicleGroup),
                ...array_keys($weightsByVehicle),
            ]));

        if ($candidateIds === []) {
            return [];
        }

        $vehicles = Vehicle::query()
            ->whereIn('id', $candidateIds)
            ->orderBy('plate')
            ->get(['id']);

        return $vehicles->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }

    /**
     * @param  list<int>  $vehicleIds
     * @return EloquentCollection<int, Vehicle>
     */
    private function loadVehiclesForOutput(array $vehicleIds): EloquentCollection
    {
        /** @var EloquentCollection<int, Vehicle> $vehicles */
        $vehicles = Vehicle::query()
            ->whereIn('id', $vehicleIds)
            ->get(['id', 'plate', 'type', 'status'])
            ->keyBy('id');

        return $vehicles;
    }

    /**
     * @param  array<string, int>  $groups
     * @return array{
     *     gross_revenue_cents: int,
     *     deductions_cents: int,
     *     net_revenue_cents: int,
     *     variable_cost_cents: int,
     *     contribution_margin_cents: int,
     *     fixed_cost_cents: int,
     *     operational_result_cents: int,
     *     admin_expense_cents: int,
     *     financial_expense_cents: int,
     *     net_result_cents: int
     * }
     */
    private function buildMetrics(array $groups): array
    {
        $grossRevenue = $groups[FinancialCategoryDreGroup::GrossRevenue->value] ?? 0;
        $deductions = $groups[FinancialCategoryDreGroup::Deductions->value] ?? 0;
        $netRevenue = $grossRevenue + $deductions;

        $variableCost = $groups[FinancialCategoryDreGroup::VariableCost->value] ?? 0;
        $contributionMargin = $netRevenue + $variableCost;

        $fixedCost = $groups[FinancialCategoryDreGroup::FixedCost->value] ?? 0;
        $operationalResult = $contributionMargin + $fixedCost;

        $adminExpense = $groups[FinancialCategoryDreGroup::AdminExpense->value] ?? 0;
        $financialExpense = $groups[FinancialCategoryDreGroup::FinancialExpense->value] ?? 0;
        $netResult = $operationalResult + $adminExpense + $financialExpense;

        return [
            'gross_revenue_cents' => $grossRevenue,
            'deductions_cents' => $deductions,
            'net_revenue_cents' => $netRevenue,
            'variable_cost_cents' => $variableCost,
            'contribution_margin_cents' => $contributionMargin,
            'fixed_cost_cents' => $fixedCost,
            'operational_result_cents' => $operationalResult,
            'admin_expense_cents' => $adminExpense,
            'financial_expense_cents' => $financialExpense,
            'net_result_cents' => $netResult,
        ];
    }

    /**
     * @param  list<array{
     *     vehicle_id: int,
     *     plate: string,
     *     type: string|null,
     *     status: string|null,
     *     groups_cents: array<string, int>,
     *     metrics: array<string, int>,
     *     km: int,
     *     consumption_km: int,
     *     liters: float,
     *     reserves: array{oil_cents: int, tire_cents: int, prudential_cents: int, driver_salary_cents: int, prolabore_cents: int, total_cents: int, params: array<string, float|int>},
     *     result_before_reserves_cents: int,
     *     economic_result_cents: int,
     *     apportionment: array{basis_value: int, basis_percent: float}
     * }>  $vehicles
     * @return array{
     *     vehicles_count: int,
     *     groups_cents: array<string, int>,
     *     gross_revenue_cents: int,
     *     deductions_cents: int,
     *     net_revenue_cents: int,
     *     variable_cost_cents: int,
     *     contribution_margin_cents: int,
     *     fixed_cost_cents: int,
     *     operational_result_cents: int,
     *     admin_expense_cents: int,
     *     financial_expense_cents: int,
     *     net_result_cents: int,
     *     km: int,
     *     liters: float,
     *     consumption: float|null,
     *     per_km: array{revenue: float, cost: float, breakeven: float|null},
     *     breakeven_km: int|null,
     *     reserves: array{oil_cents: int, tire_cents: int, prudential_cents: int, driver_salary_cents: int, prolabore_cents: int, total_cents: int},
     *     result_before_reserves_cents: int,
     *     economic_result_cents: int
     * }
     */
    private function buildTotals(array $vehicles): array
    {
        $groups = $this->emptyGroups();
        $reserves = [
            'oil_cents' => 0,
            'tire_cents' => 0,
            'prudential_cents' => 0,
            'driver_salary_cents' => 0,
            'prolabore_cents' => 0,
            'total_cents' => 0,
        ];
        $economicResult = 0;
        $resultBeforeReserves = 0;
        $metrics = [
            'gross_revenue_cents' => 0,
            'deductions_cents' => 0,
            'net_revenue_cents' => 0,
            'variable_cost_cents' => 0,
            'contribution_margin_cents' => 0,
            'fixed_cost_cents' => 0,
            'operational_result_cents' => 0,
            'admin_expense_cents' => 0,
            'financial_expense_cents' => 0,
            'net_result_cents' => 0,
        ];

        $km = 0;
        $consumptionKm = 0;
        $liters = 0.0;

        foreach ($vehicles as $vehicle) {
            foreach ($vehicle['groups_cents'] as $group => $value) {
                $groups[$group] += $value;
            }

            foreach ($metrics as $key => $value) {
                $metrics[$key] = $value + (int) ($vehicle['metrics'][$key] ?? 0);
            }

            $km += $vehicle['km'];
            $consumptionKm += $vehicle['consumption_km'];
            $liters += $vehicle['liters'];

            foreach ($reserves as $key => $value) {
                $reserves[$key] = $value + (int) $vehicle['reserves'][$key];
            }

            $resultBeforeReserves += (int) $vehicle['result_before_reserves_cents'];
            $economicResult += (int) $vehicle['economic_result_cents'];
        }

        return [
            'vehicles_count' => count($vehicles),
            'groups_cents' => $groups,
            ...$metrics,
            'km' => $km,
            'liters' => $liters,
            'consumption' => $liters > 0.0 ? round($consumptionKm / $liters, 2) : null,
            'per_km' => $this->buildPerKm($metrics, $km),
            'breakeven_km' => $this->buildBreakevenKm($metrics, $km),
            'reserves' => $reserves,
            'result_before_reserves_cents' => $resultBeforeReserves,
            'economic_result_cents' => $economicResult,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyGroups(): array
    {
        $groups = [];

        foreach (FinancialCategoryDreGroup::cases() as $group) {
            $groups[$group->value] = 0;
        }

        return $groups;
    }

    /**
     * @param  list<int>|null  $ids
     * @return list<int>|null
     */
    private function normalizeIntegerList(?array $ids, string $field): ?array
    {
        if ($ids === null) {
            return null;
        }

        $result = [];

        foreach ($ids as $id) {
            if ($id <= 0) {
                throw ValidationException::withMessages([
                    $field => 'Lista inválida: use apenas IDs inteiros positivos.',
                ]);
            }

            $result[$id] = $id;
        }

        return array_values($result);
    }
}
