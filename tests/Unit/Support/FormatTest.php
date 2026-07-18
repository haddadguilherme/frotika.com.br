<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Format;
use PHPUnit\Framework\TestCase;

final class FormatTest extends TestCase
{
    public function test_money_formata_positivo_com_simbolo(): void
    {
        $this->assertSame('R$ 1.372,00', Format::money(137200));
    }

    public function test_money_negativo_usa_sinal_de_menos_tipografico(): void
    {
        $this->assertSame('−R$ 2.596,00', Format::money(-259600));
    }

    public function test_money_com_sign_forca_mais_no_positivo(): void
    {
        $this->assertSame('+R$ 2.120,00', Format::money(212000, sign: true));
    }

    public function test_money_decimal_nao_traz_simbolo(): void
    {
        $this->assertSame('4,37', Format::moneyDecimal(4.37));
        $this->assertSame('10.243', Format::moneyDecimal(10243, 0));
    }

    public function test_liters_usa_tres_casas(): void
    {
        $this->assertSame('245,300 L', Format::liters(245.3));
    }

    public function test_km_agrupa_por_milhar_sem_casas(): void
    {
        $this->assertSame('418.900 km', Format::km(418900));
    }

    public function test_consumption_nulo_vira_travessao(): void
    {
        $this->assertSame('−', Format::consumption(null));
        $this->assertSame('2,43 km/l', Format::consumption(2.43));
    }

    public function test_percent_com_uma_casa(): void
    {
        $this->assertSame('34,0%', Format::percent(34.0));
    }

    public function test_cnpj_formata_no_padrao_da_receita(): void
    {
        $this->assertSame('11.222.333/0001-81', Format::cnpj('11222333000181'));
    }

    public function test_cpf_formata_no_padrao(): void
    {
        $this->assertSame('123.456.789-00', Format::cpf('12345678900'));
    }

    public function test_phone_celular_com_onze_digitos(): void
    {
        $this->assertSame('(11) 9 8765-4321', Format::phone('11987654321'));
        $this->assertSame('(11) 9 8765-4321', Format::phone('(11) 9 8765-4321'));
    }

    public function test_phone_fixo_com_dez_digitos(): void
    {
        $this->assertSame('(11) 3456-7890', Format::phone('1134567890'));
    }

    public function test_phone_tamanho_inesperado_volta_so_digitos(): void
    {
        $this->assertSame('123', Format::phone('123'));
        $this->assertSame('', Format::phone(null));
    }

    public function test_cte_key_em_blocos_de_quatro(): void
    {
        $key = str_repeat('1234', 11);

        $this->assertSame('1234 1234 1234 1234 1234 1234 1234 1234 1234 1234 1234', Format::cteKey($key));
    }

    public function test_date_e_datetime_em_pt_br(): void
    {
        $this->assertSame('14/03/2026', Format::date('2026-03-14'));
        $this->assertSame('14/03/2026 08:22', Format::dateTime('2026-03-14 08:22:00'));
        $this->assertSame('', Format::date(null));
    }
}
