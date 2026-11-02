<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding progress belongs to the Company, not the User: the data
 * being configured (company profile, first client/product, billing
 * setup) is company-level, and several users can share one company.
 * No `*_completed` booleans for individual checklist items — those are
 * always derived from real data (customers()->exists(), etc., see
 * OnboardingService::checklistFor()) so there is nothing here that could
 * drift out of sync with what actually exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->timestamp('onboarding_started_at')->nullable()->after('simulation_enabled');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_started_at');
            $table->string('onboarding_step', 20)->nullable()->after('onboarding_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['onboarding_started_at', 'onboarding_completed_at', 'onboarding_step']);
        });
    }
};
