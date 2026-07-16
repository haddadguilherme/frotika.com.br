<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\ShowLoginController;
use App\Http\Controllers\Tenancy\RegisterOwnerAndCompanyController;
use App\Http\Controllers\Tenancy\ShowRegisterController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::view('/', 'welcome')->name('welcome');
    Route::get('/entrar', ShowLoginController::class)->name('login');
    Route::post('/entrar', LoginController::class)->name('login.attempt');

    Route::get('/registrar', ShowRegisterController::class)->name('register');
    Route::post('/registrar', RegisterOwnerAndCompanyController::class)->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::view('/painel', 'dashboard')->name('dashboard');
    Route::post('/sair', LogoutController::class)->name('logout');
});
