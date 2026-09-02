<?php

namespace App\Providers;

use App\Contracts\AssistantProvider;
use App\Services\Assistant\PrototypeAssistantProvider;
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
        //
    }
}
