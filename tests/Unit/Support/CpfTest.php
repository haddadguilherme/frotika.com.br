<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Cpf\Cpf;
use PHPUnit\Framework\TestCase;

final class CpfTest extends TestCase
{
    public function test_valida_cpf_correto(): void
    {
        $this->assertTrue(Cpf::isValid('529.982.247-25'));
        $this->assertTrue(Cpf::isValid('11144477735'));
    }

    public function test_rejeita_cpf_invalido(): void
    {
        $this->assertFalse(Cpf::isValid('529.982.247-24'));
        $this->assertFalse(Cpf::isValid('11111111111'));
        $this->assertFalse(Cpf::isValid('123'));
        $this->assertFalse(Cpf::isValid(''));
    }

    public function test_normaliza_e_formata(): void
    {
        $this->assertSame('52998224725', Cpf::digits('529.982.247-25'));
        $this->assertSame('529.982.247-25', Cpf::format('52998224725'));
        $this->assertSame('123', Cpf::format('123'));
    }
}
