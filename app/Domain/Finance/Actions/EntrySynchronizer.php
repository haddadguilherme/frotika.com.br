<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Enums\FinancialEntryStatus;
use App\Domain\Finance\Enums\FinancialEntryType;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Domain\Trips\Enums\CteStatus;
use App\Domain\Trips\Models\CteDocument;
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

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly SeedDefaultFinancialCategories $seedDefaultFinancialCategories,
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
                'driver_id' => null,
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
