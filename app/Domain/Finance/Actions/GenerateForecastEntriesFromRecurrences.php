<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Enums\FinancialEntryStatus;
use App\Domain\Finance\Enums\RecurrenceFrequency;
use App\Domain\Finance\Models\FinancialCategory;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Finance\Models\Recurrence;
use App\Domain\Tenancy\Models\Company;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

final class GenerateForecastEntriesFromRecurrences
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @return array{
     *     recurrences_processed: int,
     *     recurrences_skipped: int,
     *     occurrences_evaluated: int,
     *     entries_created: int
     * }
     */
    public function execute(Company $company, string $referenceDate, bool $dryRun = false): array
    {
        $periodEnd = CarbonImmutable::parse($referenceDate)->endOfMonth()->startOfDay();
        $periodStart = $periodEnd->startOfMonth();

        return $this->tenant->runFor($company, function () use ($periodStart, $periodEnd, $dryRun): array {
            $recurrences = Recurrence::query()
                ->where('active', true)
                ->get();

            $recurrencesProcessed = 0;
            $recurrencesSkipped = 0;
            $occurrencesEvaluated = 0;
            $entriesCreated = 0;

            foreach ($recurrences as $recurrence) {
                $startsAt = CarbonImmutable::parse((string) $recurrence->starts_at)->startOfDay();
                $endsAt = $recurrence->ends_at === null
                    ? null
                    : CarbonImmutable::parse((string) $recurrence->ends_at)->startOfDay();

                if ($startsAt->gt($periodEnd) || ($endsAt !== null && $endsAt->lt($periodStart))) {
                    continue;
                }

                $recurrencesProcessed++;

                $category = FinancialCategory::query()->find((int) $recurrence->financial_category_id);

                if ($category === null || ! $category->active || $category->type === null) {
                    $recurrencesSkipped++;

                    continue;
                }

                if ($category->type->value !== $recurrence->type->value) {
                    $recurrencesSkipped++;

                    continue;
                }

                $generatedCount = max(
                    (int) $recurrence->installments_generated,
                    (int) FinancialEntry::query()->where('recurrence_id', $recurrence->getKey())->count(),
                );

                if ($recurrence->installments !== null && $generatedCount >= (int) $recurrence->installments) {
                    $recurrencesSkipped++;

                    continue;
                }

                $occurrenceDates = $this->occurrenceDatesUntil($recurrence, $periodEnd);

                foreach ($occurrenceDates as $occurrenceDate) {
                    if ($recurrence->installments !== null && $generatedCount >= (int) $recurrence->installments) {
                        break;
                    }

                    $occurrencesEvaluated++;

                    $alreadyExists = FinancialEntry::query()
                        ->where('recurrence_id', $recurrence->getKey())
                        ->whereDate('competence_date', $occurrenceDate->toDateString())
                        ->exists();

                    if ($alreadyExists) {
                        continue;
                    }

                    if (! $dryRun) {
                        FinancialEntry::query()->create([
                            'company_id' => $recurrence->company_id,
                            'bank_account_id' => null,
                            'financial_category_id' => $recurrence->financial_category_id,
                            'vehicle_id' => $recurrence->vehicle_id,
                            'driver_id' => $recurrence->driver_id,
                            'trip_id' => $recurrence->trip_id,
                            'type' => $recurrence->type->value,
                            'description' => (string) $recurrence->description,
                            'document_number' => $recurrence->document_number,
                            'competence_date' => $occurrenceDate->toDateString(),
                            'due_date' => $occurrenceDate->toDateString(),
                            'paid_at' => null,
                            'amount_cents' => (int) $recurrence->amount_cents,
                            'status' => FinancialEntryStatus::Forecast->value,
                            'payment_method' => $recurrence->payment_method?->value,
                            'sourceable_type' => null,
                            'sourceable_id' => null,
                            'transfer_pair_id' => null,
                            'recurrence_id' => $recurrence->getKey(),
                            'attachment_path' => null,
                            'reconciled_at' => null,
                            'created_by' => $recurrence->created_by,
                        ]);
                    }

                    $entriesCreated++;
                    $generatedCount++;
                }

                if (! $dryRun && (int) $recurrence->installments_generated !== $generatedCount) {
                    $recurrence->forceFill([
                        'installments_generated' => $generatedCount,
                    ])->save();
                }
            }

            return [
                'recurrences_processed' => $recurrencesProcessed,
                'recurrences_skipped' => $recurrencesSkipped,
                'occurrences_evaluated' => $occurrencesEvaluated,
                'entries_created' => $entriesCreated,
            ];
        });
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function occurrenceDatesUntil(Recurrence $recurrence, CarbonImmutable $periodEnd): array
    {
        $startsAt = CarbonImmutable::parse((string) $recurrence->starts_at)->startOfDay();
        $endsAt = $recurrence->ends_at === null
            ? null
            : CarbonImmutable::parse((string) $recurrence->ends_at)->startOfDay();

        $limitDate = $endsAt !== null && $endsAt->lt($periodEnd)
            ? $endsAt
            : $periodEnd;

        if ($limitDate->lt($startsAt)) {
            return [];
        }

        return match ($recurrence->frequency) {
            RecurrenceFrequency::Weekly => $this->weeklyDates($startsAt, $limitDate),
            RecurrenceFrequency::Monthly => $this->monthlyDates($startsAt, $limitDate, (int) $recurrence->day_of_month),
            RecurrenceFrequency::Yearly => $this->yearlyDates($startsAt, $limitDate, (int) $recurrence->day_of_month),
        };
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function weeklyDates(CarbonImmutable $startsAt, CarbonImmutable $limitDate): array
    {
        $dates = [];
        $cursor = $startsAt;

        while ($cursor->lte($limitDate)) {
            $dates[] = $cursor;
            $cursor = $cursor->addWeek();
        }

        return $dates;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function monthlyDates(CarbonImmutable $startsAt, CarbonImmutable $limitDate, int $dayOfMonth): array
    {
        $dates = [];
        $monthCursor = $startsAt->startOfMonth();

        while ($monthCursor->lte($limitDate)) {
            $candidateDay = min($dayOfMonth, $monthCursor->daysInMonth);
            $candidate = $monthCursor->setDay($candidateDay)->startOfDay();

            if ($candidate->gte($startsAt) && $candidate->lte($limitDate)) {
                $dates[] = $candidate;
            }

            $monthCursor = $monthCursor->addMonth();
        }

        return $dates;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function yearlyDates(CarbonImmutable $startsAt, CarbonImmutable $limitDate, int $dayOfMonth): array
    {
        $dates = [];
        $targetMonth = (int) $startsAt->month;

        for ($year = (int) $startsAt->year; $year <= (int) $limitDate->year; $year++) {
            $monthBase = CarbonImmutable::create($year, $targetMonth, 1, 0, 0, 0, $startsAt->timezone);
            $candidateDay = min($dayOfMonth, $monthBase->daysInMonth);
            $candidate = $monthBase->setDay($candidateDay)->startOfDay();

            if ($candidate->gte($startsAt) && $candidate->lte($limitDate)) {
                $dates[] = $candidate;
            }
        }

        return $dates;
    }
}
