<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_notes', function (Blueprint $table): void {
            $table->string('prefix', 10)->nullable()->after('number');
            $table->string('reason_code', 50)->nullable()->after('reason');
            $table->text('reason_text')->nullable()->after('reason_code');
            $table->string('simulated_dian_code')->nullable()->after('total');
            $table->string('simulated_cufe')->nullable()->after('simulated_dian_code');
            $table->timestamp('issued_at')->nullable()->after('simulated_cufe');
            $table->timestamp('validation_at')->nullable()->after('issued_at');
            $table->string('dian_simulation_result')->nullable()->after('validation_at');
            $table->text('dian_simulation_message')->nullable()->after('dian_simulation_result');
            $table->string('pdf_path')->nullable()->after('dian_simulation_message');
            $table->string('pdf_hash', 64)->nullable()->after('pdf_path');
            $table->timestamp('pdf_generated_at')->nullable()->after('pdf_hash');
        });
    }

    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table): void {
            $table->dropColumn([
                'prefix',
                'reason_code',
                'reason_text',
                'simulated_dian_code',
                'simulated_cufe',
                'issued_at',
                'validation_at',
                'dian_simulation_result',
                'dian_simulation_message',
                'pdf_path',
                'pdf_hash',
                'pdf_generated_at',
            ]);
        });
    }
};
