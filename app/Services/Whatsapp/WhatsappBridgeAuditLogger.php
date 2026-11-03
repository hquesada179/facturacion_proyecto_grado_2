<?php

namespace App\Services\Whatsapp;

use App\Models\User;
use App\Models\WhatsappBridgeEvent;
use App\Models\WhatsappConnection;

/**
 * Writes every whatsapp_bridge_* audit event. `context` is always a
 * small, explicit, non-sensitive set of fields — callers never pass
 * arbitrary data through, so a raw link code, an HMAC signature, or the
 * shared secret can never end up in this table by accident (mirrors
 * AssistantActionAuditLogger's own discipline).
 *
 * `message_id` is attached ONLY by recordBridgeOutcome() — the single,
 * once-per-inbound-request row WhatsappBridgeController uses both as
 * its audit trail entry AND as the idempotency/dedup record (see the
 * migration's own comment on the unique (wa_id, message_id) index).
 * Every other event here never carries a message_id, so none of them
 * can ever collide with that constraint.
 */
class WhatsappBridgeAuditLogger
{
    public function codeGenerated(User $user): void
    {
        $this->log('whatsapp_link_code_generated', $user);
    }

    public function linkInvalidAttempt(?string $waId, string $reason): void
    {
        $this->log('whatsapp_link_invalid_attempt', null, waId: $waId, context: ['reason' => $reason]);
    }

    public function linkSucceeded(User $user, WhatsappConnection $connection): void
    {
        $this->log('whatsapp_link_succeeded', $user, $connection);
    }

    public function connectionRevoked(User $user, WhatsappConnection $connection): void
    {
        $this->log('whatsapp_connection_revoked', $user, $connection);
    }

    public function bridgeDisabled(): void
    {
        $this->log('whatsapp_bridge_disabled', null);
    }

    public function signatureRejected(string $reason): void
    {
        $this->log('whatsapp_bridge_signature_rejected', null, context: ['reason' => $reason]);
    }

    /**
     * The one audit row per inbound bridge request that also serves as
     * the idempotency record: a later request with the same (wa_id,
     * message_id) finds this row and WhatsappBridgeController returns
     * its stored `response` instead of processing anything twice.
     *
     * @param  array<string, mixed>  $response  Exactly what was
     *   returned to VYA for this request — never includes a token,
     *   secret, or hash.
     */
    public function recordBridgeOutcome(
        string $waId,
        ?string $messageId,
        ?User $user,
        ?WhatsappConnection $connection,
        string $type,
        array $response,
    ): void {
        $this->log('whatsapp_bridge_message_'.$type, $user, $connection, $waId, ['response' => $response], $messageId);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(
        string $event,
        ?User $user,
        ?WhatsappConnection $connection = null,
        ?string $waId = null,
        array $context = [],
        ?string $messageId = null,
    ): void {
        WhatsappBridgeEvent::create([
            'company_id' => $user?->company_id ?? $connection?->company_id,
            'user_id' => $user?->id,
            'wa_id' => $waId ?? $connection?->wa_id,
            'message_id' => $messageId,
            'event' => $event,
            'context' => $context,
            'created_at' => now(),
        ]);
    }
}
