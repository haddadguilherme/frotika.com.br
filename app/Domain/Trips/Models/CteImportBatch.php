<?php

declare(strict_types=1);

namespace App\Domain\Trips\Models;

use App\Domain\Trips\Enums\CteImportBatchStatus;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lote de importação de CT-e em massa: guarda o progresso e o resultado por
 * arquivo para auditoria e para alimentar a tela de resultado e a notificação
 * em tempo real quando o processamento termina.
 *
 * @property CteImportBatchStatus $status
 * @property array<int, array<string, mixed>> $results
 * @property Carbon $created_at
 */
final class CteImportBatch extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => CteImportBatchStatus::class,
            'results' => 'array',
            'total_files' => 'integer',
            'processed_files' => 'integer',
            'imported_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === CteImportBatchStatus::Completed;
    }
}
