<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('payment_type', 20)->nullable()->after('due_date');
            $table->foreignId('numbering_resolution_id')->nullable()->after('company_id')
                ->constrained('numbering_resolutions')->nullOnDelete();
            $table->string('simulated_cufe')->nullable()->after('simulated_dian_code');
            $table->timestamp('issued_at')->nullable()->after('simulated_cufe');
            $table->timestamp('validation_at')->nullable()->after('issued_at');
            $table->string('dian_simulation_result')->nullable()->after('validation_at');
            $table->text('dian_simulation_message')->nullable()->after('dian_simulation_result');
            $table->json('issuer_snapshot')->nullable()->after('dian_simulation_message');
            $table->json('customer_snapshot')->nullable()->after('issuer_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('numbering_resolution_id');
            $table->dropColumn([
                'payment_type',
                'simulated_cufe',
                'issued_at',
                'validation_at',
                'dian_simulation_result',
                'dian_simulation_message',
                'issuer_snapshot',
                'customer_snapshot',
            ]);
        });
    }
};
