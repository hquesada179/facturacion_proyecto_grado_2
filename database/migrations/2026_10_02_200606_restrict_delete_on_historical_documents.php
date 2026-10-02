<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invoices, credit notes and invoice events are historical / fiscal
     * documents: they must never disappear as a side effect of deleting
     * their parent company or invoice. This replaces the original
     * cascadeOnDelete behaviour with restrictOnDelete, without touching
     * the already-versioned migrations that created these tables.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
        });

        Schema::table('invoice_events', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
        });

        Schema::table('invoice_events', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
        });
    }

    /**
     * Restore the original cascadeOnDelete behaviour.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::table('invoice_events', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
        });

        Schema::table('invoice_events', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });
    }
};
