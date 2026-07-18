<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LookupCepControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_consulta_retorna_endereco_especifico(): void
    {
        Http::fake([
            'https://viacep.com.br/*' => Http::response([
                'cep' => '01001-000',
                'logradouro' => 'Praça da Sé',
                'complemento' => 'lado ímpar',
                'bairro' => 'Sé',
                'localidade' => 'São Paulo',
                'uf' => 'SP',
                'ibge' => '3550308',
            ], 200),
        ]);

        $response = $this
            ->actingAs($this->verifiedUser())
            ->getJson('/empresas/cep/01001000');

        $response->assertOk();
        $response->assertJson([
            'status' => 'found',
            'generic' => false,
            'address' => [
                'zip_code' => '01001-000',
                'street' => 'Praça da Sé',
                'complement' => 'lado ímpar',
                'district' => 'Sé',
                'city' => 'São Paulo',
                'state' => 'SP',
                'ibge_code' => '3550308',
            ],
        ]);
    }

    public function test_consulta_marca_cep_generico_quando_nao_ha_logradouro(): void
    {
        Http::fake([
            'https://viacep.com.br/*' => Http::response([
                'cep' => '29900-000',
                'logradouro' => '',
                'complemento' => '',
                'bairro' => '',
                'localidade' => 'Linhares',
                'uf' => 'ES',
                'ibge' => '3203205',
            ], 200),
        ]);

        $response = $this
            ->actingAs($this->verifiedUser())
            ->getJson('/empresas/cep/29900000');

        $response->assertOk();
        $response->assertJsonPath('status', 'found');
        $response->assertJsonPath('generic', true);
        $response->assertJsonPath('address.city', 'Linhares');
        $response->assertJsonPath('address.state', 'ES');
        $response->assertJsonPath('address.street', null);
        $response->assertJsonPath('address.district', null);
    }

    public function test_consulta_retorna_not_found_quando_viacep_devolve_erro(): void
    {
        Http::fake([
            'https://viacep.com.br/*' => Http::response(['erro' => true], 200),
        ]);

        $response = $this
            ->actingAs($this->verifiedUser())
            ->getJson('/empresas/cep/99999999');

        $response->assertOk();
        $response->assertExactJson(['status' => 'not_found']);
    }

    public function test_consulta_retorna_unavailable_quando_viacep_falha(): void
    {
        Http::fake([
            'https://viacep.com.br/*' => Http::response(null, 500),
        ]);

        $response = $this
            ->actingAs($this->verifiedUser())
            ->getJson('/empresas/cep/01001000');

        $response->assertOk();
        $response->assertExactJson(['status' => 'unavailable']);
    }

    public function test_consulta_rejeita_cep_invalido_sem_chamar_a_api(): void
    {
        Http::fake();

        $response = $this
            ->actingAs($this->verifiedUser())
            ->getJson('/empresas/cep/0100100');

        $response->assertUnprocessable();
        $response->assertJsonPath('status', 'invalid');
        $response->assertJsonPath('message', 'O CEP informado é inválido.');

        Http::assertNothingSent();
    }

    private function verifiedUser(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }
}
