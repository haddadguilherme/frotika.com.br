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
use App\Http\Controllers\Dashboard\ShowDashboardController;
use App\Http\Controllers\Finance\CancelFinancialEntryController;
use App\Http\Controllers\Finance\DeactivateBankAccountController;
use App\Http\Controllers\Finance\ListBankAccountsController;
use App\Http\Controllers\Finance\ListFinancialEntriesController;
use App\Http\Controllers\Finance\SettleFinancialEntryController;
use App\Http\Controllers\Finance\ShowCashFlowController;
use App\Http\Controllers\Finance\ShowCreateBankAccountController;
use App\Http\Controllers\Finance\ShowCreateFinancialEntryController;
use App\Http\Controllers\Finance\ShowEditBankAccountController;
use App\Http\Controllers\Finance\ShowEditFinancialEntryController;
use App\Http\Controllers\Finance\ShowFinancialEntryController;
use App\Http\Controllers\Finance\StoreBankAccountController;
use App\Http\Controllers\Finance\StoreFinancialEntryController;
use App\Http\Controllers\Finance\UpdateBankAccountController;
use App\Http\Controllers\Finance\UpdateFinancialEntryController;
use App\Http\Controllers\Fleet\DeactivateDriverController;
use App\Http\Controllers\Fleet\DeactivateVehicleController;
use App\Http\Controllers\Fleet\ListDriversController;
use App\Http\Controllers\Fleet\ListVehiclesController;
use App\Http\Controllers\Fleet\ShowCreateDriverController;
use App\Http\Controllers\Fleet\ShowCreateVehicleController;
use App\Http\Controllers\Fleet\ShowDriverController;
use App\Http\Controllers\Fleet\ShowEditDriverController;
use App\Http\Controllers\Fleet\ShowEditVehicleController;
use App\Http\Controllers\Fleet\ShowVehicleController;
use App\Http\Controllers\Fleet\StoreDriverController;
use App\Http\Controllers\Fleet\StoreOdometerReadingController;
use App\Http\Controllers\Fleet\StoreVehicleController;
use App\Http\Controllers\Fleet\UpdateDriverController;
use App\Http\Controllers\Fleet\UpdateVehicleController;
use App\Http\Controllers\Fuelings\DeleteFuelingController;
use App\Http\Controllers\Fuelings\ListFuelingsController;
use App\Http\Controllers\Fuelings\ShowCreateFuelingController;
use App\Http\Controllers\Fuelings\ShowEditFuelingController;
use App\Http\Controllers\Fuelings\ShowFuelingController;
use App\Http\Controllers\Fuelings\StoreFuelingController;
use App\Http\Controllers\Fuelings\UpdateFuelingController;
use App\Http\Controllers\Maintenances\DeleteMaintenanceController;
use App\Http\Controllers\Maintenances\ListMaintenancesController;
use App\Http\Controllers\Maintenances\ShowCreateMaintenanceController;
use App\Http\Controllers\Maintenances\ShowEditMaintenanceController;
use App\Http\Controllers\Maintenances\ShowMaintenanceController;
use App\Http\Controllers\Maintenances\StoreMaintenanceController;
use App\Http\Controllers\Maintenances\UpdateMaintenanceController;
use App\Http\Controllers\Partners\DeactivateBusinessPartnerController;
use App\Http\Controllers\Partners\ListBusinessPartnersController;
use App\Http\Controllers\Partners\ShowBusinessPartnerController;
use App\Http\Controllers\Partners\ShowCreateBusinessPartnerController;
use App\Http\Controllers\Partners\ShowEditBusinessPartnerController;
use App\Http\Controllers\Partners\StoreBusinessPartnerController;
use App\Http\Controllers\Partners\UpdateBusinessPartnerController;
use App\Http\Controllers\Reports\ShowCostParametersController;
use App\Http\Controllers\Reports\ShowDreController;
use App\Http\Controllers\Reports\UpdateCostParametersController;
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
use App\Http\Controllers\Trips\ListCteController;
use App\Http\Controllers\Trips\ShowCteController;
use App\Http\Controllers\Trips\ShowCteImportResultController;
use App\Http\Controllers\Trips\ShowImportCteController;
use App\Http\Controllers\Trips\StoreCteImportController;
use App\Http\Middleware\EnsureGroupLicenseAllowsWrite;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Platform\Http\Controllers\CleanBackupsController;
use App\Platform\Http\Controllers\DeleteBackupFileController;
use App\Platform\Http\Controllers\DownloadBackupFileController;
use App\Platform\Http\Controllers\IssueGroupLicenseInvoiceController;
use App\Platform\Http\Controllers\ListBackupsController;
use App\Platform\Http\Controllers\ListGroupsController;
use App\Platform\Http\Controllers\MarkGroupLicenseInvoicePaidController;
use App\Platform\Http\Controllers\MonitorBackupsController;
use App\Platform\Http\Controllers\RunDatabaseBackupController;
use App\Platform\Http\Controllers\RunFullBackupController;
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
        Route::get('/painel', ShowDashboardController::class)->name('dashboard');
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

        Route::get('/parceiros', ListBusinessPartnersController::class)->name('partners.index');
        Route::get('/parceiros/novo', ShowCreateBusinessPartnerController::class)->name('partners.create');
        Route::post('/parceiros', StoreBusinessPartnerController::class)->name('partners.store');
        Route::get('/parceiros/{partner}', ShowBusinessPartnerController::class)
            ->whereNumber('partner')
            ->name('partners.show');
        Route::get('/parceiros/{partner}/editar', ShowEditBusinessPartnerController::class)
            ->whereNumber('partner')
            ->name('partners.edit');
        Route::put('/parceiros/{partner}', UpdateBusinessPartnerController::class)
            ->whereNumber('partner')
            ->name('partners.update');
        Route::delete('/parceiros/{partner}', DeactivateBusinessPartnerController::class)
            ->whereNumber('partner')
            ->name('partners.destroy');

        Route::get('/contas', ListBankAccountsController::class)->name('bank-accounts.index');
        Route::get('/contas/nova', ShowCreateBankAccountController::class)->name('bank-accounts.create');
        Route::post('/contas', StoreBankAccountController::class)->name('bank-accounts.store');
        Route::get('/contas/{account}/editar', ShowEditBankAccountController::class)
            ->whereNumber('account')
            ->name('bank-accounts.edit');
        Route::put('/contas/{account}', UpdateBankAccountController::class)
            ->whereNumber('account')
            ->name('bank-accounts.update');
        Route::delete('/contas/{account}', DeactivateBankAccountController::class)
            ->whereNumber('account')
            ->name('bank-accounts.destroy');

        Route::get('/fluxo-de-caixa', ShowCashFlowController::class)->name('cash-flow.index');

        Route::get('/dre', ShowDreController::class)->name('dre.index');
        Route::get('/dre/parametros', ShowCostParametersController::class)->name('cost-parameters.edit');
        Route::put('/dre/parametros', UpdateCostParametersController::class)->name('cost-parameters.update');

        Route::get('/lancamentos', ListFinancialEntriesController::class)->name('financial-entries.index');
        Route::get('/lancamentos/novo', ShowCreateFinancialEntryController::class)->name('financial-entries.create');
        Route::post('/lancamentos', StoreFinancialEntryController::class)->name('financial-entries.store');
        Route::get('/lancamentos/{entry}', ShowFinancialEntryController::class)
            ->whereNumber('entry')
            ->name('financial-entries.show');
        Route::get('/lancamentos/{entry}/editar', ShowEditFinancialEntryController::class)
            ->whereNumber('entry')
            ->name('financial-entries.edit');
        Route::put('/lancamentos/{entry}', UpdateFinancialEntryController::class)
            ->whereNumber('entry')
            ->name('financial-entries.update');
        Route::post('/lancamentos/{entry}/baixa', SettleFinancialEntryController::class)
            ->whereNumber('entry')
            ->name('financial-entries.settle');
        Route::delete('/lancamentos/{entry}', CancelFinancialEntryController::class)
            ->whereNumber('entry')
            ->name('financial-entries.destroy');

        Route::get('/veiculos', ListVehiclesController::class)->name('vehicles.index');
        Route::get('/veiculos/novo', ShowCreateVehicleController::class)->name('vehicles.create');
        Route::post('/veiculos', StoreVehicleController::class)->name('vehicles.store');
        Route::get('/veiculos/{vehicle}', ShowVehicleController::class)
            ->whereNumber('vehicle')
            ->name('vehicles.show');
        Route::post('/veiculos/{vehicle}/leituras', StoreOdometerReadingController::class)
            ->whereNumber('vehicle')
            ->name('vehicles.odometer-readings.store');
        Route::get('/veiculos/{vehicle}/editar', ShowEditVehicleController::class)
            ->whereNumber('vehicle')
            ->name('vehicles.edit');
        Route::put('/veiculos/{vehicle}', UpdateVehicleController::class)
            ->whereNumber('vehicle')
            ->name('vehicles.update');
        Route::delete('/veiculos/{vehicle}', DeactivateVehicleController::class)
            ->whereNumber('vehicle')
            ->name('vehicles.destroy');

        Route::get('/motoristas', ListDriversController::class)->name('drivers.index');
        Route::get('/motoristas/novo', ShowCreateDriverController::class)->name('drivers.create');
        Route::post('/motoristas', StoreDriverController::class)->name('drivers.store');
        Route::get('/motoristas/{driver}', ShowDriverController::class)
            ->whereNumber('driver')
            ->name('drivers.show');
        Route::get('/motoristas/{driver}/editar', ShowEditDriverController::class)
            ->whereNumber('driver')
            ->name('drivers.edit');
        Route::put('/motoristas/{driver}', UpdateDriverController::class)
            ->whereNumber('driver')
            ->name('drivers.update');
        Route::delete('/motoristas/{driver}', DeactivateDriverController::class)
            ->whereNumber('driver')
            ->name('drivers.destroy');

        Route::get('/abastecimentos', ListFuelingsController::class)->name('fuelings.index');
        Route::get('/abastecimentos/novo', ShowCreateFuelingController::class)->name('fuelings.create');
        Route::post('/abastecimentos', StoreFuelingController::class)->name('fuelings.store');
        Route::get('/abastecimentos/{fueling}', ShowFuelingController::class)
            ->whereNumber('fueling')
            ->name('fuelings.show');
        Route::get('/abastecimentos/{fueling}/editar', ShowEditFuelingController::class)
            ->whereNumber('fueling')
            ->name('fuelings.edit');
        Route::put('/abastecimentos/{fueling}', UpdateFuelingController::class)
            ->whereNumber('fueling')
            ->name('fuelings.update');
        Route::delete('/abastecimentos/{fueling}', DeleteFuelingController::class)
            ->whereNumber('fueling')
            ->name('fuelings.destroy');

        Route::get('/manutencoes', ListMaintenancesController::class)->name('maintenances.index');
        Route::get('/manutencoes/nova', ShowCreateMaintenanceController::class)->name('maintenances.create');
        Route::post('/manutencoes', StoreMaintenanceController::class)->name('maintenances.store');
        Route::get('/manutencoes/{maintenance}', ShowMaintenanceController::class)
            ->whereNumber('maintenance')
            ->name('maintenances.show');
        Route::get('/manutencoes/{maintenance}/editar', ShowEditMaintenanceController::class)
            ->whereNumber('maintenance')
            ->name('maintenances.edit');
        Route::put('/manutencoes/{maintenance}', UpdateMaintenanceController::class)
            ->whereNumber('maintenance')
            ->name('maintenances.update');
        Route::delete('/manutencoes/{maintenance}', DeleteMaintenanceController::class)
            ->whereNumber('maintenance')
            ->name('maintenances.destroy');

        Route::get('/ct-e', ListCteController::class)->name('cte.index');
        Route::get('/ct-e/importar', ShowImportCteController::class)->name('cte.import');
        Route::post('/ct-e/importar', StoreCteImportController::class)->name('cte.import.store');
        Route::get('/ct-e/importacoes/{batch}', ShowCteImportResultController::class)->name('cte.import.result');
        Route::get('/ct-e/{cte}', ShowCteController::class)
            ->whereNumber('cte')
            ->name('cte.show');
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

            Route::get('/backups', ListBackupsController::class)->name('backups.index');
            Route::post('/backups/executar-db', RunDatabaseBackupController::class)->name('backups.run-db');
            Route::post('/backups/executar-completo', RunFullBackupController::class)->name('backups.run-full');
            Route::post('/backups/limpar', CleanBackupsController::class)->name('backups.clean');
            Route::post('/backups/monitorar', MonitorBackupsController::class)->name('backups.monitor');
            Route::get('/backups/download', DownloadBackupFileController::class)->name('backups.download');
            Route::delete('/backups/arquivo', DeleteBackupFileController::class)->name('backups.destroy');
        });

    Route::post('/sair', LogoutController::class)->name('logout');
});
