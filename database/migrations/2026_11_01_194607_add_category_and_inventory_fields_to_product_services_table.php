<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_services', function (Blueprint $table) {
            $table->string('category', 100)->nullable()->after('description');
            $table->boolean('is_inventoried')->default(false)->after('tax_included');
            $table->decimal('stock_quantity', 12, 2)->nullable()->after('is_inventoried');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_services', function (Blueprint $table) {
            $table->dropColumn(['category', 'is_inventoried', 'stock_quantity']);
        });
    }
};
