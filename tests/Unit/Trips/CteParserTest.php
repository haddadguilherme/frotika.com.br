<?php

declare(strict_types=1);

namespace Tests\Unit\Trips;

use App\Domain\Partners\Enums\BusinessPartnerDocumentType;
use App\Domain\Trips\Cte\CteParser;
use App\Domain\Trips\Cte\Exceptions\InvalidCteException;
use App\Domain\Trips\Enums\CteServiceType;
use App\Domain\Trips\Enums\CteStatus;
use App\Domain\Trips\Enums\CteTakerRole;
use App\Domain\Trips\Enums\CteType;
use Tests\TestCase;

final class CteParserTest extends TestCase
{
    private function fixture(): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/Cte/cte-hi-transportes.xml'));
    }

    public function test_extrai_identificacao_e_chave(): void
    {
        $data = (new CteParser)->parse($this->fixture());

        $this->assertSame('52260717624719000520570050000167601000167600', $data->accessKey);
        $this->assertSame(44, strlen($data->accessKey));
        $this->assertSame('4.00', $data->layoutVersion);
        $this->assertSame(57, $data->model);
        $this->assertSame(16760, $data->number);
        $this->assertSame(5, $data->series);
        $this->assertSame(CteType::Normal, $data->cteType);
        $this->assertSame(CteServiceType::Normal, $data->serviceType);
        $this->assertSame(CteTakerRole::Sender, $data->takerRole);
        $this->assertSame('6932', $data->cfop);
        $this->assertSame('01', $data->modal);
        $this->assertSame('2026-07-13', $data->issuedAt->toDateString());
    }

    public function test_extrai_partes_com_documento(): void
    {
        $data = (new CteParser)->parse($this->fixture());

        $this->assertSame('17624719000520', $data->issuer->document);
        $this->assertSame(BusinessPartnerDocumentType::Cnpj, $data->issuer->documentType);
        $this->assertSame('HI TRANSPORTES LTDA', $data->issuer->legalName);

        $this->assertNotNull($data->sender);
        $this->assertSame('10656452006897', $data->sender->document);
        $this->assertSame('VOTORANTIM CIMENTOS NNE SA', $data->sender->legalName);

        $this->assertNotNull($data->recipient);
        $this->assertSame('07683539000131', $data->recipient->document);

        // O tomador é o remetente (toma3/toma = 0).
        $this->assertSame($data->sender, $data->taker());
    }

    public function test_extrai_valores_em_centavos_sem_float(): void
    {
        $data = (new CteParser)->parse($this->fixture());

        $this->assertSame(623889, $data->totalValueCents);
        $this->assertSame(623889, $data->receivableValueCents);
        // ICMS45 sem vICMS: zero, não erro.
        $this->assertSame(0, $data->icmsValueCents);
        $this->assertSame(3874500, $data->cargoValueCents);
        $this->assertSame('CIMENTO CP II-E 40-GRANEL', $data->cargoDescription);
        $this->assertSame('37800.0000', $data->cargoWeightKg);
    }

    public function test_extrai_trecho_veiculos_motorista_e_protocolo(): void
    {
        $data = (new CteParser)->parse($this->fixture());

        $this->assertSame('XAMBIOA', $data->originCity);
        $this->assertSame('TO', $data->originState);
        $this->assertSame('MA', $data->destinationState);
        $this->assertSame('02163740', $data->rntrc);

        $this->assertSame('GXX8D33', $data->tractorPlate);
        $this->assertSame('OWO1F78', $data->trailerPlate);
        $this->assertSame('UELINTON CAROLINO DOS SANTOS', $data->driverName);
        $this->assertSame('09831473612', $data->driverCpf);

        $this->assertSame(CteStatus::Authorized, $data->status);
        $this->assertSame('352260116414286', $data->protocolNumber);
    }

    public function test_xml_invalido_lanca_excecao(): void
    {
        $this->expectException(InvalidCteException::class);

        (new CteParser)->parse('<foo>não é cte</foo>');
    }
}
