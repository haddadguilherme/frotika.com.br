<?php

declare(strict_types=1);

namespace App\Domain\Trips\Observers;

use App\Domain\Finance\Actions\EntrySynchronizer;
use App\Domain\Trips\Models\CteDocument;

/**
 * Mantém o lançamento financeiro do CT-e em sincronia (blueprint 6.3). Qualquer
 * criação, atualização, cancelamento (soft delete) ou restauração recalcula a
 * receita via EntrySynchronizer — o CT-e nunca vira número no DRE por fora.
 */
final class CteDocumentObserver
{
    public function __construct(private readonly EntrySynchronizer $synchronizer) {}

    public function saved(CteDocument $cte): void
    {
        $this->synchronizer->syncFromCte($cte);
    }

    public function deleted(CteDocument $cte): void
    {
        $this->synchronizer->syncFromCte($cte);
    }

    public function restored(CteDocument $cte): void
    {
        $this->synchronizer->syncFromCte($cte);
    }
}
