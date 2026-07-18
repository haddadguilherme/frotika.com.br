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
use App\Http\Controllers\Tenancy\CreateCompanyController;
use App\Http\Controllers\Tenancy\DeactivateCompanyController;
use App\Http\Controllers\Tenancy\ListCompaniesController;
use App\Http\Controllers\Tenancy\LookupCepController;
use App\Http\Controllers\Tenancy\LookupCnpjController;
use App\Http\Controllers\Tenancy\RegisterOwnerAndCompanyController;
use App\Http\Controllers\Tenancy\ShowCompanyController;
use App\Http\Controllers\Tenancy\ShowCreateCompanyController;
use App\Http\Controllers\Tenancy\ShowEditCompanyController;
use App\Http\Controllers\Tenancy\ShowRegisterController;
use App\Http\Controllers\Tenancy\SwitchCurrentCompanyController;
use App\Http\Controllers\Tenancy\UpdateCompanyController;
use App\Http\Middleware\EnsureGroupLicenseAllowsWrite;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Platform\Http\Controllers\IssueGroupLicenseInvoiceController;
use App\Platform\Http\Controllers\ListGroupsController;
use App\Platform\Http\Controllers\MarkGroupLicenseInvoicePaidController;
use App\Platform\Http\Controllers\ShowGroupController;
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
    Route::get('/registrar/cnpj/{cnpj}', LookupCnpjController::class)
        ->whereNumber('cnpj')
        ->middleware('throttle:20,1')
        ->name('register.cnpj');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/confirmar-email', ShowVerifyEmailController::class)->name('verification.notice');
    Route::post('/confirmar-email/notificacao', SendVerificationEmailController::class)
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::get('/confirmar-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::middleware(['verified', EnsureGroupLicenseAllowsWrite::class])->group(function (): void {
        Route::view('/painel', 'dashboard')->name('dashboard');
        Route::post('/empresa-atual', SwitchCurrentCompanyController::class)->name('tenancy.switch-company');

        Route::get('/empresas', ListCompaniesController::class)->name('companies.index');
        Route::get('/empresas/nova', ShowCreateCompanyController::class)->name('companies.create');
        Route::post('/empresas', CreateCompanyController::class)->name('companies.store');
        Route::get('/empresas/cnpj/{cnpj}', LookupCnpjController::class)
            ->whereNumber('cnpj')
            ->middleware('throttle:20,1')
            ->name('companies.cnpj');
        Route::get('/empresas/cep/{cep}', LookupCepController::class)
            ->whereNumber('cep')
            ->middleware('throttle:30,1')
            ->name('companies.cep');
        Route::get('/empresas/{company}', ShowCompanyController::class)
            ->whereNumber('company')
            ->name('companies.show');
        Route::get('/empresas/{company}/editar', ShowEditCompanyController::class)
            ->whereNumber('company')
            ->name('companies.edit');
        Route::put('/empresas/{company}', UpdateCompanyController::class)
            ->whereNumber('company')
            ->name('companies.update');
        Route::delete('/empresas/{company}', DeactivateCompanyController::class)
            ->whereNumber('company')
            ->name('companies.destroy');
    });

    Route::middleware(['verified', EnsurePlatformAdmin::class])
        ->prefix('admin')
        ->name('platform.')
        ->group(function (): void {
            Route::get('/', ListGroupsController::class)->name('groups.index');
            Route::get('/grupos/{group}', ShowGroupController::class)->name('groups.show');
            Route::post('/licencas/{license}/boletos', IssueGroupLicenseInvoiceController::class)
                ->name('licenses.issue');
            Route::post('/boletos/{invoice}/quitar', MarkGroupLicenseInvoicePaidController::class)
                ->name('invoices.mark-paid');
        });

    Route::post('/sair', LogoutController::class)->name('logout');
});
