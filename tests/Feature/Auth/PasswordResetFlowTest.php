<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_tela_esqueci_a_senha_carrega_com_sucesso(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_envia_link_de_redefinicao_para_usuario_existente(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_redefine_senha_com_token_valido(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => 'senha-antiga-123',
        ]);

        $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $token = '';

        Notification::assertSentTo($user, ResetPassword::class, static function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nova-senha-123',
            'password_confirmation' => 'nova-senha-123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $this->assertCredentials([
            'email' => $user->email,
            'password' => 'nova-senha-123',
        ]);
    }

    public function test_nao_redefine_senha_com_token_invalido(): void
    {
        $user = User::factory()->create([
            'password' => 'senha-antiga-123',
        ]);

        $response = $this
            ->from(route('password.reset', ['token' => 'token-invalido', 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => 'token-invalido',
                'email' => $user->email,
                'password' => 'nova-senha-123',
                'password_confirmation' => 'nova-senha-123',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');

        $this->assertCredentials([
            'email' => $user->email,
            'password' => 'senha-antiga-123',
        ]);
    }
}
