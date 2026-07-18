<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Billing\Enums\GroupLicenseInvoiceStatus;
use App\Domain\Billing\Enums\GroupLicenseStatus;
use App\Domain\Billing\Models\GroupLicense;
use App\Domain\Billing\Models\GroupLicenseInvoice;
use App\Domain\Finance\Models\BankAccount;
use App\Domain\Finance\Models\FinancialEntry;
use App\Domain\Finance\Policies\BankAccountPolicy;
use App\Domain\Finance\Policies\FinancialEntryPolicy;
use App\Domain\Fleet\Models\Driver;
use App\Domain\Fleet\Models\Vehicle;
use App\Domain\Fleet\Policies\DriverPolicy;
use App\Domain\Fleet\Policies\VehiclePolicy;
use App\Domain\Fuelings\Models\Fueling;
use App\Domain\Fuelings\Observers\FuelingObserver;
use App\Domain\Fuelings\Policies\FuelingPolicy;
use App\Domain\Maintenances\Models\Maintenance;
use App\Domain\Maintenances\Observers\MaintenanceObserver;
use App\Domain\Maintenances\Policies\MaintenancePolicy;
use App\Domain\Partners\Models\BusinessPartner;
use App\Domain\Partners\Policies\BusinessPartnerPolicy;
use App\Domain\Tenancy\Models\Company;
use App\Domain\Tenancy\Models\Group;
use App\Domain\Tenancy\Observers\CompanyObserver;
use App\Domain\Tenancy\Policies\CompanyPolicy;
use App\Domain\Trips\Models\CteDocument;
use App\Domain\Trips\Observers\CteDocumentObserver;
use App\Domain\Trips\Policies\CteDocumentPolicy;
use App\Models\User;
use App\Support\Format;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Company::observe(CompanyObserver::class);
        CteDocument::observe(CteDocumentObserver::class);
        Fueling::observe(FuelingObserver::class);
        Maintenance::observe(MaintenanceObserver::class);

        // Alias global para usar Format:: direto na Blade (seção 14.3 do blueprint).
        AliasLoader::getInstance()->alias('Format', Format::class);

        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(BusinessPartner::class, BusinessPartnerPolicy::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(Driver::class, DriverPolicy::class);
        Gate::policy(Fueling::class, FuelingPolicy::class);
        Gate::policy(Maintenance::class, MaintenancePolicy::class);
        Gate::policy(BankAccount::class, BankAccountPolicy::class);
        Gate::policy(FinancialEntry::class, FinancialEntryPolicy::class);
        Gate::policy(CteDocument::class, CteDocumentPolicy::class);

        Gate::define('switch-company', static function (User $user, Company $company): bool {
            return $user->companies()->whereKey($company->getKey())->exists()
                && $user->groups()->whereKey($company->getAttribute('group_id'))->exists();
        });

        Gate::define('access-platform', static function (User $user): bool {
            return $user->isPlatformAdmin();
        });

        View::composer('layouts.app', static function ($view): void {
            $user = Auth::user();

            if (! $user instanceof User || $user->current_group_id === null) {
                $view->with('topbarCompanies', collect());
                $view->with('topbarCurrentCompanyId', null);
                $view->with('topbarCurrentCompanyName', null);
                $view->with('licenseBanner', null);
                $view->with('isPlatformAdmin', $user instanceof User && $user->isPlatformAdmin());

                return;
            }

            $companies = $user->companies()
                ->where('group_id', $user->current_group_id)
                ->orderBy('trade_name')
                ->get(['companies.id', 'companies.trade_name']);

            $currentCompanyName = $companies
                ->firstWhere('id', $user->current_company_id)
                ?->getAttribute('trade_name');

            $licenseBanner = null;

            /** @var GroupLicense|null $license */
            $license = GroupLicense::query()
                ->where('group_id', $user->current_group_id)
                ->first();

            if ($license !== null && ! in_array($license->status, [GroupLicenseStatus::Active, GroupLicenseStatus::Trialing], true)) {
                $group = Group::query()
                    ->with('owner:id,name')
                    ->find($user->current_group_id);

                /** @var GroupLicenseInvoice|null $openInvoice */
                $openInvoice = $license->invoices()
                    ->whereIn('status', [
                        GroupLicenseInvoiceStatus::Pending->value,
                        GroupLicenseInvoiceStatus::Overdue->value,
                    ])
                    ->orderBy('due_date')
                    ->first();

                $isOwner = $group !== null && (int) $group->owner_user_id === $user->getKey();

                $licenseBanner = [
                    'status_label' => $license->status->label(),
                    'status_value' => $license->status->value,
                    'due_date' => $openInvoice?->due_date,
                    'amount_cents' => $openInvoice?->amount_cents,
                    'boleto_url' => $openInvoice?->boleto_url,
                    'boleto_pdf_url' => $openInvoice?->boleto_pdf_url,
                    'owner_name' => $group?->owner?->name,
                    'is_owner' => $isOwner,
                ];
            }

            $view->with('topbarCompanies', $companies);
            $view->with('topbarCurrentCompanyId', $user->current_company_id);
            $view->with('topbarCurrentCompanyName', $currentCompanyName);
            $view->with('licenseBanner', $licenseBanner);
            $view->with('isPlatformAdmin', $user->isPlatformAdmin());
        });
    }
}
