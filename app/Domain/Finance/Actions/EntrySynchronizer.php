<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Enums\FinancialEntryStatus;
use App\Domain\Finance\Enums\FinancialEntryType;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Fuelings\Enums\FuelProduct;
use App\Domain\Fuelings\Models\Fueling;
use App\Domain\Maintenances\Enums\MaintenanceStatus;
use App\Domain\Maintenances\Models\Maintenance;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Domain\Trips\Enums\CteStatus;
use App\Domain\Trips\Models\CteDocument;
use App\Support\Format;
use App\Support\Tenancy\TenantContext;
use RuntimeException;

/**
 * Superfície única que transforma documentos de origem em financial_entries
 * (regra 7). O relatório agrega só financial_entries; nunca soma cte_documents
 * direto. Aqui o CT-e vira uma receita `forecast` (a receber), com valor igual
 * a um percentual do vTPrest — a fatia do frete que o agregado recebe da
 * contratante (regra 6: competência = emissão, sem paid_at).
 */
final class EntrySynchronizer
{
    /** Categoria analítica de receita de fretes (blueprint 14.1). */
    private const FREIGHT_REVENUE_CODE = '1.1';

    /** Custos variáveis de abastecimento (blueprint 6.3 / 14.1). */
    private const FUEL_COST_CODE = '3.1';

    private const ARLA_COST_CODE = '3.2';

    private const OIL_COST_CODE = '3.6';

    /** Manutenção corretiva (variável) e preventiva (fixo) — blueprint 6.3. */
    private const MAINTENANCE_CORRECTIVE_CODE = '3.4';

    private const MAINTENANCE_PREVENTIVE_CODE = '4.3';

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly SeedDefaultFinancialCategories $seedDefaultFinancialCategories,
        private readonly RecalculateBankAccountCurrentBalance $recalculateBankAccountCurrentBalance,
    ) {}

    public function syncFromCte(CteDocument $cte): void
    {
        $company = Company::query()->find($cte->getAttribute('company_id'));

        if (! $company instanceof Company) {
            return;
        }

        $this->tenant->runFor($company, function () use ($cte, $company): void {
            $existing = FinancialEntry::query()
                ->where('sourceable_type', CteDocument::class)
                ->where('sourceable_id', $cte->getKey())
                ->first();

            if ($this->shouldCancel($cte)) {
                if ($existing instanceof FinancialEntry && $existing->status !== FinancialEntryStatus::Canceled) {
                    $existing->update(['status' => FinancialEntryStatus::Canceled->value]);
                }

                return;
            }

            $amountCents = $this->amountCents($cte);

            if ($amountCents <= 0) {
                return;
            }

            $createdBy = $this->resolveCreatedBy($cte, $company);

            if ($createdBy === null) {
                return;
            }

            $category = $this->resolveFreightCategory($company);

            $attributes = [
                'financial_category_id' => $category->getKey(),
                'bank_account_id' => null,
                'vehicle_id' => $cte->getAttribute('vehicle_id'),
                'driver_id' => $cte->getAttribute('driver_id'),
                'trip_id' => null,
                'type' => FinancialEntryType::Revenue->value,
                'description' => $this->description($cte),
                'document_number' => (string) $cte->getAttribute('number'),
                'competence_date' => $cte->issued_at->toDateString(),
                'due_date' => $cte->issued_at->copy()->addDays($this->receivableDays())->toDateString(),
                'paid_at' => null,
                'amount_cents' => $amountCents,
                'status' => FinancialEntryStatus::Forecast->value,
                'payment_method' => null,
                'recurrence_id' => null,
            ];

            if ($existing instanceof FinancialEntry) {
                if ($existing->status === FinancialEntryStatus::Settled) {
                    // Baixa é fato de caixa; reimportar o CT-e não desfaz a
                    // liquidação já conciliada pelo usuário.
                    unset(
                        $attributes['status'],
                        $attributes['paid_at'],
                        $attributes['bank_account_id'],
                        $attributes['payment_method'],
                    );
                }

                $existing->update($attributes);

                return;
            }

            FinancialEntry::query()->create([
                'company_id' => $company->getKey(),
                'sourceable_type' => CteDocument::class,
                'sourceable_id' => $cte->getKey(),
                'created_by' => $createdBy,
                ...$attributes,
            ]);
        });
    }

    /**
     * Abastecimento vira despesa (regra 7). À vista sai do caixa na hora
     * (liquidado na conta padrão, paid_at = fueled_at); a prazo vira uma conta a
     * pagar (previsto). Uma vez liquidado/conciliado, reimportar/editar não
     * desfaz a baixa — o caixa manda.
     */
    public function syncFromFueling(Fueling $fueling): void
    {
        $company = Company::query()->find($fueling->getAttribute('company_id'));

        if (! $company instanceof Company) {
            return;
        }

        $this->tenant->runFor($company, function () use ($fueling, $company): void {
            $existing = FinancialEntry::query()
                ->where('sourceable_type', Fueling::class)
                ->where('sourceable_id', $fueling->getKey())
                ->first();

            /** @var array<int, true> $accountsToRecalculate */
            $accountsToRecalculate = [];

            if ($existing instanceof FinancialEntry) {
                $existingAccountId = $existing->getAttribute('bank_account_id');

                if ($existingAccountId !== null) {
                    $accountsToRecalculate[(int) $existingAccountId] = true;
                }
            }

            if ($fueling->trashed()) {
                if ($existing instanceof FinancialEntry && $existing->status !== FinancialEntryStatus::Canceled) {
                    $existing->update(['status' => FinancialEntryStatus::Canceled->value]);
                }

                $this->recalculateAccounts($company, $accountsToRecalculate);

                return;
            }

            $amountCents = (int) $fueling->getAttribute('total_cents');

            if ($amountCents <= 0) {
                return;
            }

            $createdBy = $this->resolveFuelingCreatedBy($fueling, $company);

            if ($createdBy === null) {
                return;
            }

            $category = $this->resolveCategoryByCode($company, $this->fuelingCategoryCode($fueling->product));
            $fueledAt = $fueling->fueled_at;

            $paymentMethod = $fueling->payment_method;
            // À vista só liquida se houver conta padrão para receber a baixa;
            // sem conta padrão, cai como conta a pagar (previsto).
            $defaultAccount = $paymentMethod->isCashLike() ? $this->defaultBankAccount() : null;
            $isSettled = $defaultAccount !== null;

            $attributes = [
                'financial_category_id' => $category->getKey(),
                'bank_account_id' => $defaultAccount?->getKey(),
                'vehicle_id' => $fueling->getAttribute('vehicle_id'),
                'driver_id' => $fueling->getAttribute('driver_id'),
                'trip_id' => $fueling->getAttribute('trip_id'),
                'type' => FinancialEntryType::Expense->value,
                'description' => $this->fuelingDescription($fueling),
                'document_number' => $fueling->getAttribute('invoice_number'),
                'competence_date' => $fueledAt->toDateString(),
                'due_date' => $fueledAt->toDateString(),
                'paid_at' => $isSettled ? $fueledAt->toDateString() : null,
                'amount_cents' => $amountCents,
                'status' => $isSettled ? FinancialEntryStatus::Settled->value : FinancialEntryStatus::Forecast->value,
                'payment_method' => $isSettled ? $paymentMethod->toFinancialEntryPaymentMethod()->value : null,
                'recurrence_id' => null,
            ];

            if ($existing instanceof FinancialEntry) {
                if ($existing->status === FinancialEntryStatus::Settled) {
                    // Baixa é fato de caixa; editar o abastecimento não desfaz a
                    // liquidação já conciliada.
                    unset(
                        $attributes['status'],
                        $attributes['paid_at'],
                        $attributes['bank_account_id'],
                        $attributes['payment_method'],
                    );
                }

                $existing->update($attributes);
            } else {
                FinancialEntry::query()->create([
                    'company_id' => $company->getKey(),
                    'sourceable_type' => Fueling::class,
                    'sourceable_id' => $fueling->getKey(),
                    'created_by' => $createdBy,
                    ...$attributes,
                ]);
            }

            if ($defaultAccount !== null) {
                $accountsToRecalculate[(int) $defaultAccount->getKey()] = true;
            }

            $this->recalculateAccounts($company, $accountsToRecalculate);
        });
    }

    /**
     * Manutenção vira despesa prevista a pagar (blueprint 6.3: paid_at nulo, o
     * usuário informa a baixa depois). Categoria 4.3 (preventiva) ou 3.4
     * (demais). Competência = fechamento, ou abertura se ainda não fechou.
     */
    public function syncFromMaintenance(Maintenance $maintenance): void
    {
        $company = Company::query()->find($maintenance->getAttribute('company_id'));

        if (! $company instanceof Company) {
            return;
        }

        $this->tenant->runFor($company, function () use ($maintenance, $company): void {
            $existing = FinancialEntry::query()
                ->where('sourceable_type', Maintenance::class)
                ->where('sourceable_id', $maintenance->getKey())
                ->first();

            $shouldCancel = $maintenance->trashed() || $maintenance->status === MaintenanceStatus::Canceled;

            if ($shouldCancel) {
                if ($existing instanceof FinancialEntry && $existing->status !== FinancialEntryStatus::Canceled) {
                    $existing->update(['status' => FinancialEntryStatus::Canceled->value]);
                }

                return;
            }

            $amountCents = (int) $maintenance->getAttribute('total_cents');

            if ($amountCents <= 0) {
                return;
            }

            $createdBy = $this->resolveMaintenanceCreatedBy($maintenance, $company);

            if ($createdBy === null) {
                return;
            }

            $code = $maintenance->type->isFixedCost()
                ? self::MAINTENANCE_PREVENTIVE_CODE
                : self::MAINTENANCE_CORRECTIVE_CODE;

            $category = $this->resolveCategoryByCode($company, $code);

            $competence = ($maintenance->closed_at ?? $maintenance->opened_at)->toDateString();

            $attributes = [
                'financial_category_id' => $category->getKey(),
                'bank_account_id' => null,
                'vehicle_id' => $maintenance->getAttribute('vehicle_id'),
                'driver_id' => null,
                'trip_id' => null,
                'type' => FinancialEntryType::Expense->value,
                'description' => $this->maintenanceDescription($maintenance),
                'document_number' => $maintenance->getAttribute('invoice_number'),
                'competence_date' => $competence,
                'due_date' => $competence,
                'paid_at' => null,
                'amount_cents' => $amountCents,
                'status' => FinancialEntryStatus::Forecast->value,
                'payment_method' => null,
                'recurrence_id' => null,
            ];

            if ($existing instanceof FinancialEntry) {
                if ($existing->status === FinancialEntryStatus::Settled) {
                    // Baixa é fato de caixa; editar a manutenção não desfaz a
                    // liquidação já conciliada.
                    unset(
                        $attributes['status'],
                        $attributes['paid_at'],
                        $attributes['bank_account_id'],
                        $attributes['payment_method'],
                    );
                }

                $existing->update($attributes);

                return;
            }

            FinancialEntry::query()->create([
                'company_id' => $company->getKey(),
                'sourceable_type' => Maintenance::class,
                'sourceable_id' => $maintenance->getKey(),
                'created_by' => $createdBy,
                ...$attributes,
            ]);
        });
    }

    private function maintenanceDescription(Maintenance $maintenance): string
    {
        $workshop = $maintenance->getAttribute('workshop_name');

        $description = sprintf(
            'Manutenção · %s · %s',
            $maintenance->type->label(),
            $maintenance->category->label(),
        );

        if (is_string($workshop) && $workshop !== '') {
            $description .= ' · '.$workshop;
        }

        return mb_substr($description, 0, 200);
    }

    private function resolveMaintenanceCreatedBy(Maintenance $maintenance, Company $company): ?int
    {
        $createdBy = $maintenance->getAttribute('created_by');

        if ($createdBy !== null) {
            return (int) $createdBy;
        }

        $group = Group::query()->find($company->getAttribute('group_id'));

        return $group?->getAttribute('owner_user_id') === null
            ? null
            : (int) $group->getAttribute('owner_user_id');
    }

    /**
     * @param  array<int, true>  $accountIds
     */
    private function recalculateAccounts(Company $company, array $accountIds): void
    {
        foreach (array_keys($accountIds) as $accountId) {
            $this->recalculateBankAccountCurrentBalance->execute($company, $accountId);
        }
    }

    private function fuelingCategoryCode(FuelProduct $product): string
    {
        return match ($product) {
            FuelProduct::Arla32 => self::ARLA_COST_CODE,
            FuelProduct::Oil => self::OIL_COST_CODE,
            default => self::FUEL_COST_CODE,
        };
    }

    private function fuelingDescription(Fueling $fueling): string
    {
        $station = $fueling->getAttribute('station_name');

        $description = sprintf(
            'Abastecimento · %s · %s',
            $fueling->product->label(),
            Format::liters((float) $fueling->getAttribute('liters')),
        );

        if (is_string($station) && $station !== '') {
            $description .= ' · '.$station;
        }

        return mb_substr($description, 0, 200);
    }

    private function defaultBankAccount(): ?BankAccount
    {
        return BankAccount::query()
            ->where('is_default', true)
            ->where('active', true)
            ->first();
    }

    private function resolveFuelingCreatedBy(Fueling $fueling, Company $company): ?int
    {
        $createdBy = $fueling->getAttribute('created_by');

        if ($createdBy !== null) {
            return (int) $createdBy;
        }

        $group = Group::query()->find($company->getAttribute('group_id'));

        return $group?->getAttribute('owner_user_id') === null
            ? null
            : (int) $group->getAttribute('owner_user_id');
    }

    private function resolveCategoryByCode(Company $company, string $code): FinancialCategory
    {
        $category = FinancialCategory::query()->where('code', $code)->first();

        if ($category instanceof FinancialCategory) {
            return $category;
        }

        // Auto-cura empresas legadas (sem plano de contas): semeia e tenta de novo.
        if (FinancialCategory::query()->count() === 0) {
            $this->seedDefaultFinancialCategories->execute($company);
            $category = FinancialCategory::query()->where('code', $code)->first();
        }

        if (! $category instanceof FinancialCategory) {
            throw new RuntimeException(sprintf('Categoria financeira (%s) não encontrada para a empresa ativa.', $code));
        }

        return $category;
    }

    private function shouldCancel(CteDocument $cte): bool
    {
        return $cte->trashed()
            || $cte->status === CteStatus::Canceled
            || $cte->status === CteStatus::Denied;
    }

    private function amountCents(CteDocument $cte): int
    {
        $percent = (float) $cte->getAttribute('applied_share_percent');
        $total = (int) $cte->getAttribute('total_value_cents');

        return (int) round($total * $percent / 100);
    }

    private function description(CteDocument $cte): string
    {
        $counterpart = $cte->getAttribute('taker_name')
            ?? $cte->getAttribute('issuer_name')
            ?? 'Frete';

        $description = sprintf(
            'CT-e %s/%s · %s',
            $cte->getAttribute('number'),
            $cte->getAttribute('series'),
            $counterpart,
        );

        return mb_substr($description, 0, 200);
    }

    private function resolveFreightCategory(Company $company): FinancialCategory
    {
        $category = $this->findFreightCategory();

        if ($category instanceof FinancialCategory) {
            return $category;
        }

        // Auto-cura empresas legadas (criadas antes do plano de contas padrão,
        // ou por seed/demo antigo): se não há plano nenhum, semeia e tenta de novo.
        if (FinancialCategory::query()->count() === 0) {
            $this->seedDefaultFinancialCategories->execute($company);
            $category = $this->findFreightCategory();
        }

        if (! $category instanceof FinancialCategory) {
            throw new RuntimeException('Categoria de receita de fretes (1.1) não encontrada para a empresa ativa.');
        }

        return $category;
    }

    private function findFreightCategory(): ?FinancialCategory
    {
        return FinancialCategory::query()
            ->where('code', self::FREIGHT_REVENUE_CODE)
            ->first();
    }

    private function resolveCreatedBy(CteDocument $cte, Company $company): ?int
    {
        $importedBy = $cte->getAttribute('imported_by');

        if ($importedBy !== null) {
            return (int) $importedBy;
        }

        $group = Group::query()->find($company->getAttribute('group_id'));

        return $group?->getAttribute('owner_user_id') === null
            ? null
            : (int) $group->getAttribute('owner_user_id');
    }

    private function receivableDays(): int
    {
        return (int) config('cte.receivable_days', 30);
    }
}
