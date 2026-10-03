<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per tax applied to an invoice line. Not a plain pivot: code,
     * name and rate are snapshotted at calculation time so historical
     * lines keep showing what was actually charged even if the tax
     * catalog entry is later renamed, re-rated or deactivated.
     */
    public function up(): void
    {
        Schema::create('invoice_item_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->decimal('base', 14, 2);
            $table->decimal('rate', 5, 2);
            $table->decimal('value', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_item_taxes');
    }
};
