<?php

namespace App\Providers;

use App\Contracts\AssistantProvider;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Assistant\PrototypeAssistantProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AssistantProvider::class, PrototypeAssistantProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Administrador: full administrative access (company settings, users, security).
        Gate::define('manage-company', fn (User $user): bool => $user->role === UserRole::Administrador);

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
    }
}
