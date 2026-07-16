<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class EmailVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_nao_verificado_e_redirecionado_ao_tentar_acessar_painel(): void
    {
        /** @var User $user */
        $user = User::factory()->unverified()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_tela_de_confirmacao_carrega_para_usuario_nao_verificado(): void
    {
        /** @var User $user */
        $user = User::factory()->unverified()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('verification.notice'));

        $response->assertOk();
    }

    public function test_reenvio_dispara_notificacao_para_usuario_nao_verificado(): void
    {
        Notification::fake();

        /** @var User $user */
        $user = User::factory()->unverified()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('verification.send'));

        $response->assertRedirect();
        $response->assertSessionHas('status');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_usuario_consegue_confirmar_email_com_link_assinado(): void
    {
        /** @var User $user */
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $response = $this
            ->actingAs($user)
            ->get($verificationUrl);

        $response->assertRedirect(route('dashboard'));

        $this->assertTrue((bool) $user->fresh()?->hasVerifiedEmail());
    }

    public function test_registro_web_envia_confirmacao_e_redireciona_para_tela_de_confirmacao(): void
    {
        Notification::fake();

        $email = 'novo-usuario@example.com';

        $response = $this->post(route('register.store'), [
            'name' => 'Maria da Silva',
            'email' => $email,
            'password' => 'senha-forte-123',
            'group_name' => 'Grupo Teste',
            'company_legal_name' => 'Transportes Teste LTDA',
            'company_trade_name' => 'Transportes Teste',
            'company_cnpj' => $this->validCnpj(),
            'tax_regime' => 'simples',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas('status');

        $user = User::query()->where('email', $email)->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    private function validCnpj(): string
    {
        $base = '191312430001';
        $firstDigit = $this->calculateCnpjDigit($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $secondDigit = $this->calculateCnpjDigit($base.$firstDigit, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return $base.$firstDigit.$secondDigit;
    }

    /**
     * @param  array<int, int>  $weights
     */
    private function calculateCnpjDigit(string $base, array $weights): int
    {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += ((int) $base[$index]) * $weight;
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
