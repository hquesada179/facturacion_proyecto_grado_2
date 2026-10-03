<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simulated DIAN numbering resolutions. Every value here is fictitious
     * (authorization_number_simulated / simulated_technical_key) — this
     * table never talks to the real DIAN and must not be confused with a
     * real resolution record.
     */
    public function up(): void
    {
        Schema::create('numbering_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 30);
            $table->string('authorization_number_simulated');
            $table->string('prefix', 4);
            $table->unsignedBigInteger('range_from');
            $table->unsignedBigInteger('range_to');
            $table->unsignedBigInteger('current_consecutive');
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('simulated_technical_key');
            $table->string('status', 20)->default('vigente');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'document_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numbering_resolutions');
    }
};
