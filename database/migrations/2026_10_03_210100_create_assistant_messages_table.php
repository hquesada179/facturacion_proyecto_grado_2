<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assistant_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->string('feedback', 20)->nullable();
            $table->timestamp('feedback_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_messages');
    }
};
