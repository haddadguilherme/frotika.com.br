<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;

final class ShowForgotPasswordController
{
    public function __invoke(): View
    {
        return view('auth.forgot-password');
    }
}
