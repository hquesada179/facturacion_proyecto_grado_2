<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A draft invoice must be creatable before any customer is chosen and
     * before a definitive number is assigned (Fase 4: wizard step 1,
     * numbering only consumed at issuance) — the original migration made
     * both columns required. Dropped and re-added nullable instead of
     * ->nullable()->change(), which would require doctrine/dbal.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'number']);
            $table->dropForeign(['customer_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['customer_id', 'number']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('company_id')
                ->constrained()->restrictOnDelete();
            $table->string('number')->nullable()->after('user_id');
            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'number']);
            $table->dropForeign(['customer_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['customer_id', 'number']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('customer_id')->after('company_id')
                ->constrained()->restrictOnDelete();
            $table->string('number')->after('user_id');
            $table->unique(['company_id', 'number']);
        });
    }
};
