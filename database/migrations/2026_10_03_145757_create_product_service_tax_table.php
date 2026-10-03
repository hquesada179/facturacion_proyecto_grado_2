<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Moves product <-> tax from a single belongsTo (product_services.tax_id)
     * to a many-to-many relation. The legacy tax_id column is intentionally
     * kept (never dropped) so historical data is preserved; existing
     * assignments are copied into the new pivot table below.
     */
    public function up(): void
    {
        Schema::create('product_service_tax', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_service_id')->constrained('product_services')->cascadeOnDelete();
            $table->foreignId('tax_id')->constrained('taxes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_service_id', 'tax_id']);
        });

        $now = now();

        DB::table('product_services')
            ->whereNotNull('tax_id')
            ->orderBy('id')
            ->get(['id', 'tax_id'])
            ->each(function (object $product) use ($now): void {
                DB::table('product_service_tax')->insert([
                    'product_service_id' => $product->id,
                    'tax_id' => $product->tax_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_service_tax');
    }
};
