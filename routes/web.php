<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SendPasswordResetLinkController;
use App\Http\Controllers\Auth\SendVerificationEmailController;
use App\Http\Controllers\Auth\ShowForgotPasswordController;
use App\Http\Controllers\Auth\ShowLoginController;
use App\Http\Controllers\Auth\ShowResetPasswordController;
use App\Http\Controllers\Auth\ShowVerifyEmailController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Tenancy\RegisterOwnerAndCompanyController;
use App\Http\Controllers\Tenancy\ShowRegisterController;
use App\Http\Controllers\Tenancy\SwitchCurrentCompanyController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::view('/', 'welcome')->name('welcome');
    Route::get('/entrar', ShowLoginController::class)->name('login');
    Route::post('/entrar', LoginController::class)->name('login.attempt');

    Route::get('/esqueci-a-senha', ShowForgotPasswordController::class)->name('password.request');
    Route::post('/esqueci-a-senha', SendPasswordResetLinkController::class)->name('password.email');
    Route::get('/redefinir-senha/{token}', ShowResetPasswordController::class)->name('password.reset');
    Route::post('/redefinir-senha', ResetPasswordController::class)->name('password.update');

    Route::get('/registrar', ShowRegisterController::class)->name('register');
    Route::post('/registrar', RegisterOwnerAndCompanyController::class)->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/confirmar-email', ShowVerifyEmailController::class)->name('verification.notice');
    Route::post('/confirmar-email/notificacao', SendVerificationEmailController::class)
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::get('/confirmar-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::middleware('verified')->group(function (): void {
        Route::view('/painel', 'dashboard')->name('dashboard');
        Route::post('/empresa-atual', SwitchCurrentCompanyController::class)->name('tenancy.switch-company');
    });

    Route::post('/sair', LogoutController::class)->name('logout');
});
