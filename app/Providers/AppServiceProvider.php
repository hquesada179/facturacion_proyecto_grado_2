<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NumberingResolution;
use App\Models\ProductService;
use App\Models\Tax;
use App\Models\User;
use App\Policies\CompanyPolicy;
use App\Policies\CreditNotePolicy;
use App\Policies\CustomerPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\NumberingResolutionPolicy;
use App\Policies\ProductServicePolicy;
use App\Policies\TaxPolicy;
use App\Services\Assistant\Contracts\AiProviderInterface;
use App\Services\Assistant\Providers\ExternalAiProvider;
use App\Services\Assistant\Providers\LocalFallbackProvider;
use App\Services\Invoices\Validation\ValidationEngine;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LocalFallbackProvider::class);
        $this->app->bind(AiProviderInterface::class, function ($app): AiProviderInterface {
            $provider = (string) config('services.assistant_ai.provider', 'local');
            $apiKey = (string) config('services.assistant_ai.api_key', '');
            $endpoint = (string) config('services.assistant_ai.endpoint', '');

            if ($provider !== 'local' && $apiKey !== '' && $endpoint !== '') {
                return $app->make(ExternalAiProvider::class);
            }

            return $app->make(LocalFallbackProvider::class);
        });

        // Without this, the container's constructor autowiring would build
        // ValidationEngine with its bare `$rules = []` default and silently
        // run zero rules instead of ValidationEngine::defaultRules().
        $this->app->bind(ValidationEngine::class, static fn (): ValidationEngine => ValidationEngine::default());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Administrador: full administrative access (company settings, users, security).
        Gate::define('manage-company', fn (User $user): bool => $user->role === UserRole::Administrador);

        // Administrador: numbering resolutions configuration (simulated only).
        Gate::define('manage-numbering', fn (User $user): bool => $user->role === UserRole::Administrador);

        // Administrador: tax catalog administration.
        Gate::define('manage-taxes', fn (User $user): bool => $user->role === UserRole::Administrador);

        // Administrador + Facturador: customers, products, invoices and credit notes.
        // Contador and Auditor are read-only and are never granted this ability.
        Gate::define(
            'manage-invoicing',
            fn (User $user): bool => in_array($user->role, [UserRole::Administrador, UserRole::Facturador], true)
        );

        // Administrador + Auditor: read access to the document traceability log.
        // Contador's read access is limited to documents and reports (no trace log).
        Gate::define(
            'view-traceability',
            fn (User $user): bool => in_array($user->role, [UserRole::Administrador, UserRole::Auditor], true)
        );

        // Administrador, Auditor and Contador can read company-level
        // documentary indicators. Facturador can read reports too, but
        // report services restrict sensitive metrics to their own documents.
        Gate::define(
            'view-reports',
            fn (User $user): bool => $user->company_id !== null
                && in_array($user->role, [UserRole::Administrador, UserRole::Auditor, UserRole::Contador, UserRole::Facturador], true)
        );

        Gate::define(
            'view-user-report',
            fn (User $user): bool => in_array($user->role, [UserRole::Administrador, UserRole::Auditor], true)
        );

        Gate::define(
            'use-assistant',
            fn (User $user): bool => $user->company_id !== null
                && in_array($user->role, [UserRole::Administrador, UserRole::Auditor, UserRole::Contador, UserRole::Facturador], true)
        );

        RateLimiter::for('assistant', function (Request $request): Limit {
            return Limit::perMinute(20)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(CreditNote::class, CreditNotePolicy::class);
        Gate::policy(NumberingResolution::class, NumberingResolutionPolicy::class);
        Gate::policy(Tax::class, TaxPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(ProductService::class, ProductServicePolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
    }
}
