<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trips;

use App\Domain\Trips\Models\CteDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadCteXmlController
{
    public function __invoke(Request $request, int $cte): StreamedResponse
    {
        $document = CteDocument::query()->findOrFail($cte);

        Gate::authorize('view', $document);

        $path = (string) $document->getAttribute('xml_path');
        $disk = (string) config('cte.storage_disk', 'local');

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->download(
            $path,
            $document->getAttribute('access_key').'.xml',
            ['Content-Type' => 'application/xml'],
        );
    }
}
