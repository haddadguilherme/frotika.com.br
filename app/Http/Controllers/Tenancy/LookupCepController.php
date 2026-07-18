<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use App\Support\Cep\Cep;
use App\Support\Cep\CepLookup;
use App\Support\Cep\CepLookupStatus;
use Illuminate\Http\JsonResponse;

final class LookupCepController
{
    public function __invoke(string $cep, CepLookup $lookup): JsonResponse
    {
        $digits = Cep::digits($cep);

        if (! Cep::isValid($digits)) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'O CEP informado é inválido.',
            ], 422);
        }

        $result = $lookup->find($digits);

        if ($result->status === CepLookupStatus::Found && $result->data !== null) {
            return response()->json([
                'status' => CepLookupStatus::Found->value,
                'generic' => $result->data->isGeneric(),
                'address' => $result->data->toFormPayload(),
            ]);
        }

        return response()->json([
            'status' => $result->status->value,
        ]);
    }
}
