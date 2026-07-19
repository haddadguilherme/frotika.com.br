<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Domain\Finance\Models\BankAccount;
use App\Domain\Reports\Dre\DreBuilder;
use App\Domain\Tenancy\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Painel operacional (home). Traz números reais do mês corrente por competência:
 * saldo de caixa consolidado (contas bancárias), receita/custo/resultado do DRE
 * e o comparativo da frota — o pior veículo primeiro, que é o momento "aha".
 * Sem regra de negócio própria: só agrega o que o DreBuilder e as contas já dão.
 */
final class ShowDashboardController
{
    public function __construct(private readonly DreBuilder $dre) {}

    public function __invoke(Request $request, TenantContext $tenant): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        // O middleware SetTenantContext já resolveu a empresa ativa (sessão ou
        // padrão do usuário); usar a mesma garante que saldo e DRE batem.
        $company = $tenant->company() ?? Company::query()->find($user->current_company_id);

        if (! $company instanceof Company) {
            return redirect()
                ->route('companies.index')
                ->with('warning', 'Selecione ou cadastre uma empresa para ver o painel.');
        }

        $from = CarbonImmutable::now()->startOfMonth();
        $to = CarbonImmutable::now()->endOfMonth();

        $dre = $this->dre->execute($company, $from->toDateString(), $to->toDateString());
        $totals = $dre['totals'];

        $grossRevenue = (int) $totals['gross_revenue_cents'];
        $netResult = (int) $totals['net_result_cents'];

        // Custos do mês = tudo que não é receita bruta na competência (deduções +
        // variáveis + fixos + admin + financeiro). Sempre positivo na tela.
        $costs = $grossRevenue - $netResult;

        $consolidatedBalance = (int) BankAccount::query()
            ->where('active', true)
            ->sum('current_balance_cents');

        // Frota ordenada pelo pior resultado de caixa (asc), como no DRE.
        $vehicles = $dre['vehicles'];
        usort(
            $vehicles,
            static fn (array $a, array $b): int => $a['metrics']['net_result_cents'] <=> $b['metrics']['net_result_cents'],
        );

        return view('dashboard', [
            'periodLabel' => Str::ucfirst($from->locale('pt_BR')->isoFormat('MMMM [de] YYYY')),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'kpis' => [
                'balance_cents' => $consolidatedBalance,
                'revenue_cents' => $grossRevenue,
                'costs_cents' => $costs,
                'result_cents' => $netResult,
            ],
            'totals' => $totals,
            'vehicles' => $vehicles,
            'selected' => $vehicles[0] ?? null,
        ]);
    }
}
