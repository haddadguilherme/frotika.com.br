<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class LoginController
{
    public function __invoke(LoginRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $attempted = Auth::attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
        ], (bool) ($validated['remember'] ?? false));

        if (! $attempted) {
            return redirect()
                ->back()
                ->withInput($request->safe()->only(['email']))
                ->withErrors([
                    'email' => 'Nao foi possivel entrar com esse e-mail e senha.',
                ]);
        }

        $request->session()->regenerate();

        $user = $request->user();

        if ($user !== null) {
            $user->forceFill([
                'last_login_at' => now(),
            ])->save();
        }

        return redirect()->intended(route('dashboard'));
    }
}
