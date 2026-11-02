<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IA 2.0-D audit trail: assistant_action_proposed / _confirmed /
     * _cancelled / _expired / _failed. Deliberately separate from the
     * domain's own event logs (InvoiceEvent, CreditNoteEvent via their
     * TraceLoggers) — those still fire unchanged on top of this when a
     * confirmed action actually creates/mutates a domain record; this
     * table is only ever about the assistant's own propose/confirm
     * lifecycle. Never stores the raw token, a secret, or an API key —
     * see AssistantActionAuditLogger.
     */
    public function up(): void
    {
        Schema::create('assistant_action_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->index();
            $table->foreignId('user_id')->index();
            $table->foreignId('assistant_action_proposal_id')->nullable()->index();
            $table->string('event', 60);
            $table->string('action', 60);
            $table->json('context')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_action_events');
    }
};
