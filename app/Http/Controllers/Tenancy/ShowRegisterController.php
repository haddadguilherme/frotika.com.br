<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenancy;

use Illuminate\Contracts\View\View;

final class ShowRegisterController
{
    public function __invoke(): View
    {
        return view('auth.register');
    }
}
