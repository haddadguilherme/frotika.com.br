<?php

declare(strict_types=1);

namespace App\Domain\Trips\Cte;

use App\Domain\Partners\Enums\BusinessPartnerDocumentType;
use App\Domain\Trips\Cte\Exceptions\InvalidCteException;
use App\Domain\Trips\Data\CteData;
use App\Domain\Trips\Data\CtePartyData;
use App\Domain\Trips\Enums\CteServiceType;
use App\Domain\Trips\Enums\CteStatus;
use App\Domain\Trips\Enums\CteTakerRole;
use App\Domain\Trips\Enums\CteType;
use Carbon\CarbonImmutable;

/**
 * Traduz um XML de CT-e 4.00 em CteData. Concentra as "armadilhas" do layout
 * (regra 9): namespace, encoding, chave via Id/chCTe, dinheiro sem float,
 * ICMS que varia de grupo, tomador via toma3/toma4, placas em ObsCont.
 */
final class CteParser
{
    public function parse(string $xml): CteData
    {
        $reader = CteReader::fromString($xml);

        $accessKey = $this->accessKey($reader);

        $total = $this->toCents($reader->value('vPrest/vTPrest'));
        $receivable = $reader->value('vPrest/vRec') !== null
            ? $this->toCents($reader->value('vPrest/vRec'))
            : $total;

        return new CteData(
            accessKey: $accessKey,
            layoutVersion: $reader->attribute('versao'),
            model: $this->toIntOrNull($reader->value('ide/mod')),
            number: (int) ($reader->value('ide/nCT') ?? 0),
            series: (int) ($reader->value('ide/serie') ?? 0),
            cteType: CteType::fromCode($reader->value('ide/tpCTe') ?? '0'),
            serviceType: CteServiceType::fromCode($reader->value('ide/tpServ') ?? '0'),
            takerRole: $this->takerRole($reader),
            modal: $reader->value('ide/modal'),
            cfop: $reader->value('ide/CFOP'),
            operationNature: $reader->value('ide/natOp'),
            issuedAt: $this->issuedAt($reader->value('ide/dhEmi')),
            issuer: $this->requireParty($reader, 'emit', 'enderEmit'),
            sender: $this->party($reader, 'rem', 'enderReme'),
            dispatcher: $this->party($reader, 'exped', 'enderExped'),
            receiver: $this->party($reader, 'receb', 'enderReceb'),
            recipient: $this->party($reader, 'dest', 'enderDest'),
            originCity: $reader->value('ide/xMunIni'),
            originState: $reader->value('ide/UFIni'),
            originIbge: $reader->value('ide/cMunIni'),
            destinationCity: $reader->value('ide/xMunFim'),
            destinationState: $reader->value('ide/UFFim'),
            destinationIbge: $reader->value('ide/cMunFim'),
            totalValueCents: $total,
            receivableValueCents: $receivable,
            icmsValueCents: $this->toCents($reader->deepValue('vICMS')),
            cargoValueCents: $reader->deepValue('vCarga') !== null
                ? $this->toCents($reader->deepValue('vCarga'))
                : null,
            cargoWeightKg: $this->cargoWeight($reader),
            cargoDescription: $reader->deepValue('proPred'),
            rntrc: $this->digitsOrNull($reader->deepValue('RNTRC')),
            referencedKey: $this->digitsOrNull($reader->deepValue('chCTe')),
            status: CteStatus::fromProtocolCode($reader->protocolValue('cStat')),
            protocolNumber: $reader->protocolValue('nProt'),
            tractorPlate: $this->plate($reader->obsCont('placa')),
            trailerPlate: $this->plate($reader->obsCont('placa2')),
            driverName: $reader->obsCont('motorista'),
            driverCpf: $this->digitsOrNull($reader->obsCont('cpf_motorista')),
        );
    }

    private function accessKey(CteReader $reader): string
    {
        $key = $reader->protocolValue('chCTe');

        if ($key === null) {
            $id = $reader->attribute('Id');
            $key = $id === null ? null : (preg_replace('/\D+/', '', $id) ?? '');
        }

        $key = $key === null ? '' : (preg_replace('/\D+/', '', $key) ?? '');

        if (strlen($key) !== 44) {
            throw InvalidCteException::missingAccessKey();
        }

        return $key;
    }

    private function requireParty(CteReader $reader, string $group, string $addressElement): CtePartyData
    {
        $party = $this->party($reader, $group, $addressElement);

        if ($party === null) {
            throw InvalidCteException::missingInfCte();
        }

        return $party;
    }

    private function party(CteReader $reader, string $group, string $addressElement): ?CtePartyData
    {
        if ($reader->node($group) === null) {
            return null;
        }

        $rawDocument = $reader->value($group.'/CNPJ') ?? $reader->value($group.'/CPF');
        $document = $this->digitsOrNull($rawDocument);
        $addressPath = $group.'/'.$addressElement;

        $legalName = $reader->value($group.'/xNome')
            ?? $reader->value($group.'/xFant')
            ?? 'Parceiro sem nome';

        return new CtePartyData(
            document: $document,
            documentType: BusinessPartnerDocumentType::fromDigits($document),
            legalName: mb_substr($legalName, 0, 150),
            tradeName: $this->truncate($reader->value($group.'/xFant'), 150),
            stateRegistration: $this->truncate($reader->value($group.'/IE'), 20),
            phone: $this->digitsOrNull($reader->value($group.'/fone')),
            zipCode: $this->digitsOrNull($reader->value($addressPath.'/CEP')),
            street: $this->truncate($reader->value($addressPath.'/xLgr'), 150),
            number: $this->truncate($reader->value($addressPath.'/nro'), 20),
            complement: $this->truncate($reader->value($addressPath.'/xCpl'), 80),
            district: $this->truncate($reader->value($addressPath.'/xBairro'), 80),
            city: $this->truncate($reader->value($addressPath.'/xMun'), 80),
            state: $this->truncate($reader->value($addressPath.'/UF'), 2),
            ibgeCode: $this->truncate($reader->value($addressPath.'/cMun'), 7),
        );
    }

    private function takerRole(CteReader $reader): ?CteTakerRole
    {
        $code = $reader->value('ide/toma3/toma') ?? $reader->value('ide/toma4/toma');

        if ($code === null && $reader->node('ide/toma4') !== null) {
            $code = '4';
        }

        return $code === null ? null : CteTakerRole::fromCode($code);
    }

    private function cargoWeight(CteReader $reader): ?string
    {
        $value = $reader->xpathValue(
            './/*[local-name()="infQ"][*[local-name()="cUnid"]="01"]/*[local-name()="qCarga"]',
        );

        if ($value === null) {
            $value = $reader->xpathValue(
                './/*[local-name()="infQ"][*[local-name()="tpMed"][contains(., "PESO")]]/*[local-name()="qCarga"]',
            );
        }

        if ($value === null) {
            return null;
        }

        $normalized = str_replace(',', '.', $value);

        return is_numeric($normalized) ? $normalized : null;
    }

    private function issuedAt(?string $value): CarbonImmutable
    {
        if ($value === null) {
            return CarbonImmutable::now();
        }

        return CarbonImmutable::parse($value);
    }

    /**
     * Converte um decimal de dinheiro ("6238.89") em centavos sem passar por
     * float — regra 1 do projeto (ponto flutuante acumula erro).
     */
    private function toCents(?string $value): int
    {
        if ($value === null || trim($value) === '') {
            return 0;
        }

        $normalized = str_replace(',', '.', trim($value));

        if (preg_match('/^-?\d+(\.\d+)?$/', $normalized) !== 1) {
            return 0;
        }

        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');

        $parts = explode('.', $normalized);
        $integer = $parts[0];
        $fraction = substr(($parts[1] ?? '').'00', 0, 2);

        $cents = ((int) $integer) * 100 + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    private function toIntOrNull(?string $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function digitsOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits === '' ? null : $digits;
    }

    private function plate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $plate = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '');

        return $plate === '' ? null : mb_substr($plate, 0, 8);
    }

    private function truncate(?string $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $length);
    }
}
