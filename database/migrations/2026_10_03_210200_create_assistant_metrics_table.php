<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assistant_conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assistant_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('screen')->nullable();
            $table->string('provider')->nullable();
            $table->string('model_identifier')->nullable();
            $table->string('status', 30);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->json('tool_names')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_metrics');
    }
};
