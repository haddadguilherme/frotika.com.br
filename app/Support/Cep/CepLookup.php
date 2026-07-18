<?php

declare(strict_types=1);

namespace App\Support\Cep;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Consulta endereço por CEP no ViaCEP. Não decide nada de negócio — só busca e
 * normaliza.
 *
 * - CEP válido e existente → Found com os dados.
 * - CEP válido mas inexistente (ViaCEP responde {"erro": true}) → NotFound.
 * - Rede/indisponibilidade/HTTP de erro → Unavailable.
 */
final class CepLookup
{
    private const TIMEOUT_SECONDS = 10;

    public function find(string $cep): CepLookupResult
    {
        $digits = Cep::digits($cep);

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->acceptJson()
                ->get("https://viacep.com.br/ws/{$digits}/json/");
        } catch (ConnectionException) {
            return CepLookupResult::unavailable();
        }

        if ($response->failed()) {
            return CepLookupResult::unavailable();
        }

        $data = (array) $response->json();

        if (filter_var($data['erro'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return CepLookupResult::notFound();
        }

        return CepLookupResult::found(new CepData(
            zipCode: $this->str($data, 'cep') ?? Cep::format($digits),
            street: $this->str($data, 'logradouro'),
            complement: $this->str($data, 'complemento'),
            district: $this->str($data, 'bairro'),
            city: $this->str($data, 'localidade'),
            state: $this->str($data, 'uf'),
            ibgeCode: $this->str($data, 'ibge'),
        ));
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function str(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
