<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Domain\Billing\Enums\GroupLicenseInvoiceStatus;
use App\Domain\Billing\Enums\GroupLicenseStatus;
use App\Domain\Billing\Models\GroupLicense;
use App\Domain\Billing\Models\GroupLicenseInvoice;
use App\Domain\Tenancy\Enums\GroupType;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Models\User;
use App\Notifications\Billing\GroupLicenseInvoiceDueTodayNotification;
use App\Notifications\Billing\GroupLicenseInvoiceIssuedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PlatformBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_da_plataforma_lista_grupos_cadastrados(): void
    {
        $admin = $this->createPlatformAdmin();
        [$group] = $this->createCustomerScenario();

        $response = $this
            ->actingAs($admin)
            ->get(route('platform.groups.index'));

        $response->assertOk();
        $response->assertSee($group->name);
    }

    public function test_admin_da_plataforma_ve_detalhe_do_grupo(): void
    {
        $admin = $this->createPlatformAdmin();
        [$group, $company] = $this->createCustomerScenario();

        $response = $this
            ->actingAs($admin)
            ->get(route('platform.groups.show', ['group' => $group->getKey()]));

        $response->assertOk();
        $response->assertSee($company->trade_name);
        $response->assertSee('Lançar boleto');
    }

    public function test_usuario_comum_nao_acessa_o_painel_da_plataforma(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['is_platform_admin' => false]);

        $response = $this
            ->actingAs($user)
            ->get(route('platform.groups.index'));

        $response->assertForbidden();
    }

    public function test_admin_da_plataforma_lanca_boleto_manual_para_licenca_de_um_cliente(): void
    {
        Notification::fake();
        Storage::fake('local');

        $admin = $this->createPlatformAdmin();
        [$group, , $license] = $this->createCustomerScenario();
        /** @var User $owner */
        $owner = $group->owner()->firstOrFail();

        $boletoFile = UploadedFile::fake()->create('boleto-julho.pdf', 128, 'application/pdf');

        $response = $this
            ->actingAs($admin)
            ->post(route('platform.licenses.issue', ['license' => $license->getKey()]), [
                'amount_reais' => '129,90',
                'due_date' => now()->addDays(3)->toDateString(),
                'reference_month' => now()->format('Y-m'),
                'boleto_number' => '34191.79001 01043.510047 91020.150008 9 99990000012990',
                'boleto_file' => $boletoFile,
            ]);

        $response->assertRedirect(route('platform.groups.show', ['group' => $group->getKey()]));
        $response->assertSessionHas('status', 'Boleto lançado com sucesso para o grupo.');

        $invoice = GroupLicenseInvoice::query()
            ->where('group_license_id', $license->getKey())
            ->firstOrFail();

        $this->assertSame($group->getKey(), (int) $invoice->getAttribute('group_id'));
        $this->assertSame(12990, (int) $invoice->getAttribute('amount_cents'));
        $this->assertSame(GroupLicenseInvoiceStatus::Pending, $invoice->status);
        $this->assertSame($admin->getKey(), (int) $invoice->getAttribute('created_by_user_id'));

        $storedBoletoPath = $invoice->getAttribute('boleto_file_path');
        $this->assertIsString($storedBoletoPath);
        $this->assertNotSame('', $storedBoletoPath);
        $this->assertTrue(Storage::disk('local')->exists($storedBoletoPath));

        Notification::assertSentTo(
            $owner,
            GroupLicenseInvoiceIssuedNotification::class,
            function (GroupLicenseInvoiceIssuedNotification $notification) use ($owner): bool {
                $mail = $notification->toMail($owner);

                return str_contains($mail->subject, 'Novo boleto da licença do grupo')
                    && count($mail->attachments) === 1;
            }
        );

        $this->assertDatabaseHas('group_licenses', [
            'id' => $license->getKey(),
            'status' => GroupLicenseStatus::PendingPayment->value,
        ]);
    }

    public function test_emissao_de_boleto_e_bloqueada_antes_do_fim_do_trial(): void
    {
        $admin = $this->createPlatformAdmin();
        [$group, , $license] = $this->createCustomerScenario(trialEnded: false);

        $response = $this
            ->actingAs($admin)
            ->from(route('platform.groups.show', ['group' => $group->getKey()]))
            ->post(route('platform.licenses.issue', ['license' => $license->getKey()]), [
                'amount_reais' => '99,00',
                'due_date' => now()->addDays(2)->toDateString(),
            ]);

        $response->assertRedirect(route('platform.groups.show', ['group' => $group->getKey()]));
        $response->assertSessionHasErrors(['due_date']);

        $this->assertDatabaseCount('group_license_invoices', 0);
    }

    public function test_admin_da_plataforma_da_baixa_manual_e_ativa_a_licenca(): void
    {
        $admin = $this->createPlatformAdmin();
        [$group, , $license] = $this->createCustomerScenario();

        $invoice = GroupLicenseInvoice::query()->create([
            'group_license_id' => $license->getKey(),
            'group_id' => $group->getKey(),
            'reference_month' => now()->startOfMonth()->toDateString(),
            'amount_cents' => 15990,
            'due_date' => now()->addDays(1)->toDateString(),
            'status' => GroupLicenseInvoiceStatus::Pending,
            'boleto_url' => 'https://pagamentos.exemplo.com/boleto/15990',
        ]);

        $license->forceFill(['status' => GroupLicenseStatus::PendingPayment])->save();

        $response = $this
            ->actingAs($admin)
            ->post(route('platform.invoices.mark-paid', ['invoice' => $invoice->getKey()]), [
                'paid_at' => now()->toDateString(),
                'paid_note' => 'Conferido no banco manualmente',
            ]);

        $response->assertRedirect(route('platform.groups.show', ['group' => $group->getKey()]));
        $response->assertSessionHas('status', 'Pagamento confirmado manualmente com sucesso.');

        $this->assertDatabaseHas('group_license_invoices', [
            'id' => $invoice->getKey(),
            'status' => GroupLicenseInvoiceStatus::Paid->value,
            'paid_note' => 'Conferido no banco manualmente',
            'confirmed_by_user_id' => $admin->getKey(),
        ]);

        $this->assertDatabaseHas('group_licenses', [
            'id' => $license->getKey(),
            'status' => GroupLicenseStatus::Active->value,
        ]);

        $this->assertNotNull($license->fresh()->activated_at);
    }

    public function test_usuario_comum_nao_consegue_lancar_boleto(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['is_platform_admin' => false]);
        [, , $license] = $this->createCustomerScenario();

        $response = $this
            ->actingAs($user)
            ->post(route('platform.licenses.issue', ['license' => $license->getKey()]), [
                'amount_reais' => '99,00',
                'due_date' => now()->addDays(2)->toDateString(),
            ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('group_license_invoices', 0);
    }

    public function test_comando_notifica_owner_no_dia_do_vencimento_quando_boleto_ainda_nao_foi_pago(): void
    {
        Notification::fake();

        [, , $license] = $this->createCustomerScenario();
        $invoice = GroupLicenseInvoice::query()->create([
            'group_license_id' => $license->getKey(),
            'group_id' => $license->getAttribute('group_id'),
            'reference_month' => now()->startOfMonth()->toDateString(),
            'amount_cents' => 9900,
            'due_date' => now()->toDateString(),
            'status' => GroupLicenseInvoiceStatus::Pending,
        ]);

        /** @var User $owner */
        $owner = $invoice->group()->firstOrFail()->owner()->firstOrFail();

        $this->artisan('frotika:notify-group-license-due-today')
            ->expectsOutputToContain('Notificacoes de vencimento enviadas')
            ->assertSuccessful();

        Notification::assertSentTo(
            $owner,
            GroupLicenseInvoiceDueTodayNotification::class,
            fn (GroupLicenseInvoiceDueTodayNotification $notification): bool => str_contains(
                $notification->toMail($owner)->subject,
                'Boleto da licença vence hoje'
            )
        );
    }

    public function test_comando_nao_notifica_quando_boleto_ja_foi_marcado_como_pago(): void
    {
        Notification::fake();

        [, , $license] = $this->createCustomerScenario();
        $invoice = GroupLicenseInvoice::query()->create([
            'group_license_id' => $license->getKey(),
            'group_id' => $license->getAttribute('group_id'),
            'reference_month' => now()->startOfMonth()->toDateString(),
            'amount_cents' => 9900,
            'due_date' => now()->toDateString(),
            'status' => GroupLicenseInvoiceStatus::Paid,
            'paid_at' => now(),
        ]);

        /** @var User $owner */
        $owner = $invoice->group()->firstOrFail()->owner()->firstOrFail();

        $this->artisan('frotika:notify-group-license-due-today')
            ->expectsOutputToContain('Nenhum boleto pendente vencendo hoje para notificar.')
            ->assertSuccessful();

        Notification::assertNotSentTo($owner, GroupLicenseInvoiceDueTodayNotification::class);
    }

    private function createPlatformAdmin(): User
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'is_platform_admin' => true,
            'email_verified_at' => now(),
        ]);

        return $admin;
    }

    /**
     * @return array{Group, Company, GroupLicense}
     */
    private function createCustomerScenario(bool $trialEnded = true): array
    {
        $owner = User::factory()->create();

        $group = Group::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Grupo Cliente Teste',
            'type' => GroupType::Customer->value,
            'owner_user_id' => $owner->getKey(),
            'status' => 'active',
        ]);

        $license = GroupLicense::query()->create([
            'group_id' => $group->getKey(),
            'status' => GroupLicenseStatus::Trialing,
            'trial_starts_at' => now()->subDays(8),
            'trial_ends_at' => $trialEnded ? now()->subDay() : now()->addDay(),
            'monthly_price_cents' => 9900,
        ]);

        $company = Company::query()->create([
            'group_id' => $group->getKey(),
            'uuid' => Str::uuid()->toString(),
            'cnpj' => '55111222000110',
            'legal_name' => 'Cliente Alfa LTDA',
            'trade_name' => 'Cliente Alfa',
            'tax_regime' => 'simples',
        ]);

        $group->users()->attach($owner->getKey(), [
            'role' => 'owner',
            'invited_by' => null,
            'joined_at' => now(),
        ]);

        $owner->companies()->attach($company->getKey());

        return [$group, $company, $license];
    }
}
