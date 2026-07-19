<?php

declare(strict_types=1);

namespace App\Domain\Trips\Jobs;

use App\Domain\Tenancy\Models\Company;
use App\Domain\Trips\Actions\ImportCte;
use App\Domain\Trips\Cte\Exceptions\InvalidCteException;
use App\Domain\Trips\Enums\CteImportBatchStatus;
use App\Domain\Trips\Enums\CteImportItemStatus;
use App\Domain\Trips\Events\CteBulkImportCompleted;
use App\Domain\Trips\Models\CteImportBatch;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Importa UM arquivo de CT-e do lote. Um job por arquivo, processados um a um
 * pela fila para evitar corrida entre importações da mesma empresa. Um XML
 * inválido nunca derruba os demais: a falha é registrada no lote e o job
 * conclui com sucesso. Quando o último arquivo é processado, transmite a
 * notificação de conclusão.
 *
 * Regra de tenancy (AGENTS.md #5): recebe company_id explícito e abre o
 * contexto com TenantContext::runFor(). O conteúdo do XML nunca vai para a
 * fila — só o caminho no storage.
 */
final class ImportCteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $companyId,
        public readonly int $userId,
        public readonly int $batchId,
        public readonly string $storageDisk,
        public readonly string $storagePath,
        public readonly string $originalName,
    ) {}

    public function handle(TenantContext $tenant, ImportCte $import): void
    {
        $company = Company::query()->find($this->companyId);

        if (! $company instanceof Company) {
            return;
        }

        $tenant->runFor($company, function () use ($company, $import): void {
            $this->process($company, $import);
        });
    }

    private function process(Company $company, ImportCte $import): void
    {
        $user = User::query()->find($this->userId);
        $disk = Storage::disk($this->storageDisk);

        $status = CteImportItemStatus::Imported;
        $message = null;
        $cteId = null;
        $accessKey = null;

        try {
            if (! $user instanceof User || ! $disk->exists($this->storagePath)) {
                throw InvalidCteException::unreadable();
            }

            $cte = $import->execute($user, $company, (string) $disk->get($this->storagePath), $this->originalName);
            $cteId = (int) $cte->getKey();
            $accessKey = (string) $cte->getAttribute('access_key');
        } catch (InvalidCteException $exception) {
            $status = CteImportItemStatus::Failed;
            $message = $exception->getMessage();
        } catch (Throwable $exception) {
            report($exception);
            $status = CteImportItemStatus::Failed;
            $message = 'Erro inesperado ao processar o arquivo. A equipe foi notificada.';
        } finally {
            $disk->delete($this->storagePath);
        }

        $this->recordResult($status, $message, $cteId, $accessKey);
    }

    private function recordResult(CteImportItemStatus $status, ?string $message, ?int $cteId, ?string $accessKey): void
    {
        $completed = DB::transaction(function () use ($status, $message, $cteId, $accessKey): bool {
            $batch = CteImportBatch::query()->lockForUpdate()->find($this->batchId);

            if (! $batch instanceof CteImportBatch) {
                return false;
            }

            $results = $batch->results ?? [];
            $results[] = [
                'file' => $this->originalName,
                'status' => $status->value,
                'message' => $message,
                'cte_id' => $cteId,
                'access_key' => $accessKey,
            ];

            $batch->results = $results;
            $batch->processed_files = $batch->processed_files + 1;

            if ($status === CteImportItemStatus::Imported) {
                $batch->imported_count = $batch->imported_count + 1;
            } else {
                $batch->failed_count = $batch->failed_count + 1;
            }

            $isDone = $batch->processed_files >= $batch->total_files;

            if ($isDone) {
                $batch->status = CteImportBatchStatus::Completed;
            }

            $batch->save();

            return $isDone;
        });

        if (! $completed) {
            return;
        }

        $batch = CteImportBatch::query()->find($this->batchId);

        if ($batch instanceof CteImportBatch) {
            CteBulkImportCompleted::dispatch(
                $this->userId,
                (string) $batch->getAttribute('uuid'),
                $batch->total_files,
                $batch->imported_count,
                $batch->failed_count,
            );
        }
    }
}
