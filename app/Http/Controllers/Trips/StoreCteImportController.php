<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trips;

use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Domain\Trips\Enums\CteImportBatchStatus;
use App\Domain\Trips\Jobs\ImportCteJob;
use App\Domain\Trips\Models\CteImportBatch;
use App\Http\Requests\Trips\BulkImportCteRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Recebe até 20 XMLs de uma vez, guarda cada um na área privada do grupo e
 * enfileira um ImportCteJob por arquivo. O processamento roda em segundo plano
 * (um a um) e, ao terminar, o usuário é avisado por websocket. A resposta
 * redireciona para a tela de acompanhamento do lote.
 */
final class StoreCteImportController
{
    public function __invoke(BulkImportCteRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $company = Company::query()->find($user->current_company_id);

        if (! $company instanceof Company) {
            return redirect()
                ->route('companies.index')
                ->with('warning', 'Selecione uma empresa ativa antes de importar CT-e.');
        }

        /** @var list<UploadedFile> $files */
        $files = array_values($request->file('xmls', []));

        $disk = (string) config('cte.storage_disk', 'local');
        $group = Group::query()->find($company->getAttribute('group_id'));
        $groupUuid = (string) ($group?->getAttribute('uuid') ?? 'sem-grupo');
        $batchUuid = (string) Str::uuid();

        /** @var CteImportBatch $batch */
        $batch = CteImportBatch::query()->create([
            'uuid' => $batchUuid,
            'imported_by' => $user->getKey(),
            'total_files' => count($files),
            'status' => CteImportBatchStatus::Processing,
            'results' => [],
        ]);

        foreach ($files as $index => $file) {
            $path = sprintf('grupos/%s/cte-import/%s/%02d.xml', $groupUuid, $batchUuid, $index + 1);

            Storage::disk($disk)->put($path, (string) file_get_contents($file->getRealPath()));

            ImportCteJob::dispatch(
                (int) $company->getKey(),
                (int) $user->getKey(),
                (int) $batch->getKey(),
                $disk,
                $path,
                $file->getClientOriginalName(),
            );
        }

        return redirect()
            ->route('cte.import.result', ['batch' => $batchUuid])
            ->with('status', count($files) === 1
                ? 'Arquivo enviado. Estamos processando em segundo plano — você será avisado ao concluir.'
                : count($files).' arquivos enviados. Estamos processando em segundo plano — você será avisado ao concluir.');
    }
}
