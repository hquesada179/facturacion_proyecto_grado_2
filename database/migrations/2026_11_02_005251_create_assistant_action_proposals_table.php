<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IA 2.0-D: a persisted, server-signed proposal for an assistant
     * action awaiting human confirmation ("Prepárame una factura...").
     * Generalizes the Cache-based single-action token that previously
     * only backed `issue_invoice` — see AssistantActionProposal /
     * AssistantActionService. token_hash (never the raw token) is the
     * only thing stored; the raw token is returned to the client once,
     * in the API response, and never logged or persisted anywhere.
     */
    public function up(): void
    {
        Schema::create('assistant_action_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->index();
            $table->foreignId('user_id')->index();
            $table->foreignId('assistant_conversation_id')->nullable()->index();
            $table->string('action', 60);
            $table->string('status', 20)->default('pending');
            $table->string('token_hash', 64)->unique();
            $table->json('payload');
            $table->json('preview');
            $table->json('result')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'status']);
        });

        // Separate from conversation_memory (read-only query context, IA
        // 2.0-C): the in-progress, not-yet-proposed action draft a
        // multi-turn exchange is filling in ("¿qué productos?" / "2
        // camisas" / "mejor 3") — see AiAssistantService's
        // pending-action handling. Deliberately its own column so a
        // read-only query turn never clobbers it and vice versa.
        Schema::table('assistant_conversations', function (Blueprint $table): void {
            $table->json('pending_action')->nullable()->after('conversation_memory');
        });
    }

    public function down(): void
    {
        Schema::table('assistant_conversations', function (Blueprint $table): void {
            $table->dropColumn('pending_action');
        });

        Schema::dropIfExists('assistant_action_proposals');
    }
};
