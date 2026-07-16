<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class ResetPasswordController
{
    public function __invoke(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->validated(),
            static function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return redirect()
                ->back()
                ->withInput($request->safe()->only(['email']))
                ->withErrors([
                    'email' => 'Nao foi possivel redefinir sua senha com este link. Solicite um novo e-mail.',
                ]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Senha redefinida com sucesso. Entre com sua nova senha.');
    }
}
