<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wa_id <-> user_id <-> company_id lives exclusively here — VYA never
 * stores which WhatsApp number belongs to which company (see Fase 1
 * report). `wa_id` is kept forever for audit/history even after
 * revocation; `active_wa_id` mirrors it ONLY while the connection is
 * active (revoke sets it back to null) and carries the real uniqueness
 * constraint, so a single WhatsApp number can never be the *active*
 * link for two companies at once, while still allowing a revoked row to
 * remain for history and a brand-new connection to be created later for
 * the same number. Multiple NULLs never conflict in a unique index on
 * any of SQLite/PostgreSQL/MySQL, so this needs no driver-specific
 * partial-index SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('wa_id');
            $table->string('active_wa_id')->nullable()->unique();
            $table->string('phone_number_id')->nullable();
            // Set by the bridge after every AiAssistantService::handle()
            // call so the next inbound message continues the same
            // conversation/pending_action instead of starting a fresh
            // one. No FK constraint on purpose: conversations keep
            // existing even if this pointer goes stale, and
            // AiAssistantService::conversationFor() already re-validates
            // company_id/user_id itself before trusting it.
            $table->unsignedBigInteger('assistant_conversation_id')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('wa_id');
            $table->index(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_connections');
    }
};
