<?php

declare(strict_types=1);

namespace Tests\Feature\Partners;

use App\Domain\Billing\Enums\GroupLicenseStatus;
use App\Domain\Billing\Models\GroupLicense;
use App\Domain\Partners\Enums\BusinessPartnerDocumentType;
use App\Domain\Partners\Enums\BusinessPartnerKind;
use App\Domain\Partners\Models\BusinessPartner;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BusinessPartnerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_cadastra_parceiro_com_cnpj(): void
    {
        [$owner] = $this->createOwnerWithCompany();

        $response = $this
            ->actingAs($owner)
            ->post(route('partners.store'), [
                'kind' => BusinessPartnerKind::GasStation->value,
                'document' => '11.222.333/0001-81',
                'legal_name' => 'Posto Estrada LTDA',
                'trade_name' => 'Posto Estrada',
                'phone' => '(62) 3333-4444',
                'city' => 'Goiânia',
                'state' => 'go',
            ]);

        $partner = BusinessPartner::withoutGlobalScopes()->where('document', '11222333000181')->firstOrFail();

        $response->assertRedirect(route('partners.show', ['partner' => $partner->getKey()]));
        $this->assertSame(BusinessPartnerKind::GasStation, $partner->kind);
        $this->assertSame(BusinessPartnerDocumentType::Cnpj, $partner->document_type);
        $this->assertSame('6233334444', $partner->getAttribute('phone'));
        $this->assertSame('GO', $partner->getAttribute('state'));
    }

    public function test_documento_duplicado_e_rejeitado(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();
        $this->makePartner($company, '11222333000181', 'Posto Existente');

        $response = $this
            ->actingAs($owner)
            ->from(route('partners.create'))
            ->post(route('partners.store'), [
                'kind' => BusinessPartnerKind::GasStation->value,
                'document' => '11222333000181',
                'legal_name' => 'Outro Posto',
            ]);

        $response->assertRedirect(route('partners.create'));
        $response->assertSessionHasErrors(['document']);
    }

    public function test_cnpj_invalido_e_rejeitado(): void
    {
        [$owner] = $this->createOwnerWithCompany();

        $response = $this
            ->actingAs($owner)
            ->from(route('partners.create'))
            ->post(route('partners.store'), [
                'kind' => BusinessPartnerKind::Other->value,
                'document' => '11111111111111',
                'legal_name' => 'CNPJ Torto LTDA',
            ]);

        $response->assertRedirect(route('partners.create'));
        $response->assertSessionHasErrors(['document']);
    }

    public function test_membro_sem_papel_de_gestao_nao_cadastra(): void
    {
        [, , $group] = $this->createOwnerWithCompany();
        $member = $this->createMember($group, 'manager');

        $this->actingAs($member)->get(route('partners.create'))->assertForbidden();

        $this->actingAs($member)
            ->post(route('partners.store'), [
                'kind' => BusinessPartnerKind::Other->value,
                'legal_name' => 'Não Autorizada',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('business_partners', ['legal_name' => 'Não Autorizada']);
    }

    public function test_owner_edita_parceiro(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();
        $partner = $this->makePartner($company, '11222333000181', 'Nome Antigo');

        $response = $this
            ->actingAs($owner)
            ->put(route('partners.update', ['partner' => $partner->getKey()]), [
                'kind' => BusinessPartnerKind::Contractor->value,
                'document' => '11222333000181',
                'legal_name' => 'Contratante Nova LTDA',
                'default_freight_share_percent' => '80',
            ]);

        $response->assertRedirect(route('partners.show', ['partner' => $partner->getKey()]));
        $this->assertDatabaseHas('business_partners', [
            'id' => $partner->getKey(),
            'legal_name' => 'Contratante Nova LTDA',
            'kind' => BusinessPartnerKind::Contractor->value,
            'default_freight_share_percent' => '80.00',
        ]);
    }

    public function test_desativa_parceiro(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();
        $partner = $this->makePartner($company, '11222333000181', 'Descartável');

        $response = $this
            ->actingAs($owner)
            ->delete(route('partners.destroy', ['partner' => $partner->getKey()]));

        $response->assertRedirect(route('partners.index'));
        $this->assertSoftDeleted('business_partners', ['id' => $partner->getKey()]);
    }

    public function test_listagem_mostra_apenas_parceiros_da_empresa_ativa(): void
    {
        [$owner, $company] = $this->createOwnerWithCompany();
        $this->makePartner($company, '11222333000181', 'Parceiro Visível');

        $otherOwner = User::factory()->create();
        $otherGroup = $this->createGroup($otherOwner);
        $otherCompany = $this->createCompany($otherGroup, '55111222000305');
        $this->makePartner($otherCompany, '55111222000305', 'Parceiro de Outra Empresa');

        $response = $this->actingAs($owner)->get(route('partners.index'));

        $response->assertOk();
        $response->assertSee('Parceiro Visível');
        $response->assertDontSee('Parceiro de Outra Empresa');
    }

    private function makePartner(Company $company, string $document, string $legalName): BusinessPartner
    {
        return app(TenantContext::class)->runFor($company, fn (): BusinessPartner => BusinessPartner::query()->create([
            'document' => $document,
            'document_type' => BusinessPartnerDocumentType::fromDigits($document)->value,
            'legal_name' => $legalName,
            'kind' => BusinessPartnerKind::Other->value,
            'active' => true,
        ]));
    }

    /**
     * @return array{User, Company, Group}
     */
    private function createOwnerWithCompany(): array
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);

        GroupLicense::query()->create([
            'group_id' => $group->getKey(),
            'status' => GroupLicenseStatus::Active,
            'trial_starts_at' => now()->subDays(30),
            'activated_at' => now()->subDays(20),
            'monthly_price_cents' => 9900,
        ]);

        $company = $this->createCompany($group, '55111222000112');

        $group->forceFill(['primary_company_id' => $company->getKey()])->save();

        $group->users()->attach($owner->getKey(), [
            'role' => 'owner',
            'invited_by' => null,
            'joined_at' => now(),
        ]);

        $owner->companies()->attach($company->getKey());
        $owner->forceFill([
            'current_group_id' => $group->getKey(),
            'current_company_id' => $company->getKey(),
        ])->save();

        return [$owner, $company, $group];
    }

    private function createMember(Group $group, string $role): User
    {
        $member = User::factory()->create();

        $group->users()->attach($member->getKey(), [
            'role' => $role,
            'invited_by' => null,
            'joined_at' => now(),
        ]);

        $primaryCompanyId = (int) $group->refresh()->primary_company_id;
        $member->companies()->attach($primaryCompanyId);
        $member->forceFill([
            'current_group_id' => $group->getKey(),
            'current_company_id' => $primaryCompanyId,
        ])->save();

        return $member;
    }

    private function createGroup(User $owner): Group
    {
        return Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo '.Str::random(5),
            'type' => 'customer',
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);
    }

    private function createCompany(Group $group, string $cnpj): Company
    {
        return Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => $cnpj,
            'legal_name' => 'Empresa '.$cnpj,
            'trade_name' => 'Empresa '.$cnpj,
            'tax_regime' => 'simples',
        ]);
    }
}
