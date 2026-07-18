<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\Actions\RegisterOwnerAndCompany;
use App\Domain\Tenancy\Data\RegisterOwnerAndCompanyData;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RegisterOwnerAndCompanyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_registrar_cria_onboarding_e_retorna_201(): void
    {
        $response = $this->postJson('/registrar', [
            'name' => 'Guilherme',
            'email' => 'guilherme-http@example.com',
            'password' => 'secret-1234',
            'group_name' => 'Transportes Serra Azul',
            'company_legal_name' => 'Transportes Serra Azul LTDA',
            'company_trade_name' => 'Serra Azul',
            'company_cnpj' => '11222333000181',
            'tax_regime' => 'simples',
            'company_zip_code' => '01310-100',
            'company_street' => 'Avenida Paulista',
            'company_number' => '1000',
            'company_complement' => 'Conjunto 101',
            'company_district' => 'Bela Vista',
            'company_city' => 'Sao Paulo',
            'company_state' => 'sp',
            'company_phone' => '(11) 99999-0000',
            'company_email' => 'contato@serraazul.com.br',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['user_id', 'group_id', 'company_id']);

        $this->assertDatabaseHas('users', [
            'email' => 'guilherme-http@example.com',
        ]);

        $this->assertDatabaseHas('groups', [
            'name' => 'Transportes Serra Azul',
        ]);

        $this->assertDatabaseHas('companies', [
            'cnpj' => '11222333000181',
            'legal_name' => 'Transportes Serra Azul LTDA',
            'zip_code' => '01310-100',
            'street' => 'Avenida Paulista',
            'number' => '1000',
            'complement' => 'Conjunto 101',
            'district' => 'Bela Vista',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'phone' => '11999990000',
            'email' => 'contato@serraazul.com.br',
        ]);
    }

    public function test_endpoint_registrar_retorna_422_quando_payload_e_invalido(): void
    {
        $response = $this->postJson('/registrar', [
            'name' => '',
            'email' => 'invalido',
            'password' => '123',
            'group_name' => '',
            'company_legal_name' => '',
            'company_trade_name' => '',
            'company_cnpj' => '123',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'Os dados informados sao invalidos.');
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'name',
                'email',
                'password',
                'group_name',
                'company_legal_name',
                'company_trade_name',
                'company_cnpj',
            ],
        ]);

        $this->assertArrayHasKey('errors', $response->json());
        $this->assertEqualsCanonicalizing([
            'name',
            'email',
            'password',
            'group_name',
            'company_legal_name',
            'company_trade_name',
            'company_cnpj',
        ], array_keys($response->json('errors')));
        $response->assertJsonPath('errors.name.0', 'O campo nome e obrigatorio.');
        $response->assertJsonPath('errors.email.0', 'Informe um e-mail valido.');
    }

    public function test_endpoint_registrar_retorna_422_quando_cnpj_e_semanticamente_invalido(): void
    {
        $response = $this->postJson('/registrar', [
            'name' => 'Guilherme',
            'email' => 'guilherme-invalid-cnpj@example.com',
            'password' => 'secret-1234',
            'group_name' => 'Transportes Serra Azul',
            'company_legal_name' => 'Transportes Serra Azul LTDA',
            'company_trade_name' => 'Serra Azul',
            'company_cnpj' => '12345678000191',
            'tax_regime' => 'simples',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'Os dados informados sao invalidos.');
        $response->assertJsonStructure([
            'message',
            'errors' => ['company_cnpj'],
        ]);
        $response->assertJsonPath('errors.company_cnpj.0', 'O CNPJ da empresa informado e invalido.');

        $this->assertDatabaseMissing('users', [
            'email' => 'guilherme-invalid-cnpj@example.com',
        ]);
        $this->assertDatabaseCount('groups', 0);
        $this->assertDatabaseCount('companies', 0);
    }

    public function test_endpoint_registrar_retorna_422_quando_email_ja_existe(): void
    {
        User::factory()->create([
            'email' => 'existente@example.com',
        ]);

        $response = $this->postJson('/registrar', [
            'name' => 'Novo Usuario',
            'email' => 'Existente@Example.com',
            'password' => 'secret-1234',
            'group_name' => 'Grupo Novo',
            'company_legal_name' => 'Empresa Nova LTDA',
            'company_trade_name' => 'Empresa Nova',
            'company_cnpj' => '22333444000181',
            'tax_regime' => 'simples',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'Os dados informados sao invalidos.');
        $response->assertJsonStructure([
            'message',
            'errors' => ['email'],
        ]);
        $response->assertJsonPath('errors.email.0', 'Este e-mail ja esta cadastrado.');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('groups', 0);
        $this->assertDatabaseCount('companies', 0);
    }

    public function test_endpoint_registrar_retorna_422_quando_cnpj_ja_existe(): void
    {
        $owner = User::factory()->create();

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo Existente',
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '33444555000174',
            'legal_name' => 'Empresa Existente',
            'trade_name' => 'Existente',
            'tax_regime' => 'simples',
        ]);

        $response = $this->postJson('/registrar', [
            'name' => 'Novo Usuario',
            'email' => 'novo@example.com',
            'password' => 'secret-1234',
            'group_name' => 'Outro Grupo',
            'company_legal_name' => 'Outra Empresa LTDA',
            'company_trade_name' => 'Outra Empresa',
            'company_cnpj' => '33444555000174',
            'tax_regime' => 'simples',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'Os dados informados sao invalidos.');
        $response->assertJsonStructure([
            'message',
            'errors' => ['company_cnpj'],
        ]);
        $response->assertJsonPath('errors.company_cnpj.0', 'Este CNPJ ja esta cadastrado.');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('groups', 1);
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_cria_estrutura_inicial_de_tenancy_para_owner(): void
    {
        $action = app(RegisterOwnerAndCompany::class);

        $result = $action->execute(new RegisterOwnerAndCompanyData(
            userName: 'Guilherme',
            userEmail: 'guilherme@example.com',
            password: 'secret-1234',
            groupName: 'Transportes Serra Azul',
            companyLegalName: 'Transportes Serra Azul LTDA',
            companyTradeName: 'Serra Azul',
            companyCnpj: '12345678000190',
            companyZipCode: '01310-100',
            companyStreet: 'Avenida Paulista',
            companyNumber: '1000',
            companyComplement: 'Conjunto 101',
            companyDistrict: 'Bela Vista',
            companyCity: 'Sao Paulo',
            companyState: 'SP',
            companyPhone: '11999990000',
            companyEmail: 'contato@serraazul.com.br',
        ));

        $this->assertDatabaseHas('users', [
            'email' => 'guilherme@example.com',
            'current_group_id' => $result->group->getKey(),
            'current_company_id' => $result->company->getKey(),
        ]);

        $this->assertDatabaseHas('groups', [
            'id' => $result->group->getKey(),
            'owner_user_id' => $result->user->getKey(),
            'primary_company_id' => $result->company->getKey(),
            'type' => 'customer',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('companies', [
            'id' => $result->company->getKey(),
            'group_id' => $result->group->getKey(),
            'cnpj' => '12345678000190',
            'tax_regime' => 'simples',
            'zip_code' => '01310-100',
            'street' => 'Avenida Paulista',
            'number' => '1000',
            'complement' => 'Conjunto 101',
            'district' => 'Bela Vista',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'phone' => '11999990000',
            'email' => 'contato@serraazul.com.br',
        ]);

        $this->assertDatabaseHas('group_user', [
            'group_id' => $result->group->getKey(),
            'user_id' => $result->user->getKey(),
            'role' => 'owner',
        ]);

        $this->assertDatabaseHas('company_user', [
            'company_id' => $result->company->getKey(),
            'user_id' => $result->user->getKey(),
        ]);

        $this->assertDatabaseHas('group_licenses', [
            'group_id' => $result->group->getKey(),
            'status' => 'trialing',
            'monthly_price_cents' => (int) config('billing.group_license_monthly_price_cents', 9900),
        ]);

        $this->assertDatabaseCount('group_licenses', 1);

        $this->assertDatabaseHas('bank_accounts', [
            'company_id' => $result->company->getKey(),
            'name' => 'Caixa',
            'type' => 'cash',
            'initial_balance_cents' => 0,
            'current_balance_cents' => 0,
            'is_default' => true,
            'active' => true,
        ]);

        $this->assertDatabaseHas('financial_categories', [
            'company_id' => $result->company->getKey(),
            'code' => '1.1',
            'name' => 'Receita de fretes',
            'type' => 'revenue',
            'dre_group' => 'gross_revenue',
            'allocation' => 'vehicle_direct',
            'is_system' => true,
            'active' => true,
        ]);

        $this->assertDatabaseHas('financial_categories', [
            'company_id' => $result->company->getKey(),
            'code' => '4.8',
            'name' => 'Depreciacao',
            'affects_cashflow' => false,
            'is_system' => true,
        ]);

        $this->assertDatabaseHas('financial_categories', [
            'company_id' => $result->company->getKey(),
            'code' => '8.4',
            'name' => 'Transferencia entre contas',
            'dre_group' => 'non_operating',
            'allocation' => 'non_vehicle',
            'is_system' => true,
        ]);

        $this->assertDatabaseCount('financial_categories', 46);
    }

    public function test_faz_rollback_quando_cnpj_duplicado_dispara_erro(): void
    {
        $existingOwner = User::factory()->create();

        $existingGroup = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo Existente',
            'type' => 'customer',
            'owner_user_id' => $existingOwner->getKey(),
            'status' => 'active',
        ]);

        Company::query()->create([
            'group_id' => $existingGroup->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '12345678000999',
            'legal_name' => 'Empresa Existente',
            'trade_name' => 'Existente',
            'tax_regime' => 'simples',
        ]);

        $action = app(RegisterOwnerAndCompany::class);

        $this->expectException(QueryException::class);

        try {
            $action->execute(new RegisterOwnerAndCompanyData(
                userName: 'Novo Dono',
                userEmail: 'novo-dono@example.com',
                password: 'secret-1234',
                groupName: 'Novo Grupo',
                companyLegalName: 'Nova Empresa LTDA',
                companyTradeName: 'Nova Empresa',
                companyCnpj: '12345678000999',
            ));
        } finally {
            $this->assertDatabaseMissing('users', [
                'email' => 'novo-dono@example.com',
            ]);

            $this->assertDatabaseCount('groups', 1);
            $this->assertDatabaseCount('companies', 1);
            $this->assertDatabaseCount('group_user', 0);
            $this->assertDatabaseCount('company_user', 0);
            $this->assertDatabaseCount('group_licenses', 0);
            $this->assertDatabaseCount('bank_accounts', 0);
            $this->assertDatabaseCount('financial_categories', 0);
        }
    }
}
