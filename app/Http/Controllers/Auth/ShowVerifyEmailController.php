<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ShowVerifyEmailController
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $user->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-email');
    }
}
