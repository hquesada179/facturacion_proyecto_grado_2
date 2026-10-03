<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('verification_token', 80)->nullable()->unique()->after('simulated_cufe');
            $table->string('pdf_path')->nullable()->after('customer_snapshot');
            $table->string('pdf_hash', 64)->nullable()->after('pdf_path');
            $table->timestamp('pdf_generated_at')->nullable()->after('pdf_hash');
            $table->string('delivery_status', 30)->nullable()->after('pdf_generated_at');
            $table->timestamp('delivery_simulated_at')->nullable()->after('delivery_status');
            $table->text('delivery_error')->nullable()->after('delivery_simulated_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique(['verification_token']);
            $table->dropColumn([
                'verification_token',
                'pdf_path',
                'pdf_hash',
                'pdf_generated_at',
                'delivery_status',
                'delivery_simulated_at',
                'delivery_error',
            ]);
        });
    }
};
