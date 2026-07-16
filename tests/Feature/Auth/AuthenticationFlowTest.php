<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_anonimo_e_redirecionado_ao_tentar_acessar_painel(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_usuario_consegue_entrar_com_credenciais_validas(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'password' => 'senha-valida-123',
        ]);

        $response = $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'senha-valida-123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_usuario_nao_entra_com_credenciais_invalidas(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'password' => 'senha-valida-123',
        ]);

        $response = $this
            ->from(route('login'))
            ->post(route('login.attempt'), [
                'email' => $user->email,
                'password' => 'senha-invalida-123',
            ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_usuario_autenticado_consegue_sair(): void
    {
        /** @var User $user */
        $user = User::factory()->createOne();

        $response = $this
            ->actingAs($user)
            ->post(route('logout'));

        $response->assertRedirect(route('welcome'));
        $this->assertGuest();
    }
}
