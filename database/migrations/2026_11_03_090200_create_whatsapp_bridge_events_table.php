<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail for the VYA bridge — same shape/intent as
 * assistant_action_events (see AssistantActionAuditLogger), but
 * company_id/user_id are nullable here: a signature rejection or a
 * message from a not-yet-linked wa_id has no resolved user/company at
 * all. `context` is a small allow-listed set of non-sensitive fields —
 * never the signature, the shared secret, or a plain-text link code
 * (see WhatsappBridgeAuditLogger).
 *
 * The unique (wa_id, message_id) pair is this table's second job:
 * Meta's at-least-once webhook delivery means the same inbound message
 * can arrive twice, and this is what WhatsappBridgeController uses to
 * detect that and return the original result instead of processing (and
 * potentially linking/confirming) it again. Multiple NULLs never
 * conflict in a unique index, so messages with no message_id are simply
 * never deduplicated this way (nothing to key on).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_bridge_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('wa_id')->nullable();
            $table->string('message_id')->nullable();
            $table->string('event', 60);
            $table->json('context')->nullable();
            $table->timestamp('created_at');

            $table->unique(['wa_id', 'message_id']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_bridge_events');
    }
};
