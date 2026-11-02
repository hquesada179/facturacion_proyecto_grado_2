<?php

namespace App\Services\Assistant\Actions;

use App\Models\AssistantActionEvent;
use App\Models\AssistantActionProposal;
use App\Models\User;

/**
 * Writes the five assistant_action_* audit events IA 2.0-D requires.
 * `context` is a small, explicit allow-list of non-sensitive fields —
 * callers never get to pass arbitrary data through to it, so a raw
 * token/secret/API key can never end up in this table by accident.
 */
class AssistantActionAuditLogger
{
    public function proposed(User $user, AssistantActionProposal $proposal): void
    {
        $this->log($user, $proposal, 'assistant_action_proposed');
    }

    public function confirmed(User $user, AssistantActionProposal $proposal): void
    {
        $this->log($user, $proposal, 'assistant_action_confirmed', [
            'created_resource_type' => $proposal->result['resource_type'] ?? null,
            'created_resource_id' => $proposal->result['resource_id'] ?? null,
        ]);
    }

    public function cancelled(User $user, AssistantActionProposal $proposal): void
    {
        $this->log($user, $proposal, 'assistant_action_cancelled');
    }

    public function expired(User $user, AssistantActionProposal $proposal): void
    {
        $this->log($user, $proposal, 'assistant_action_expired');
    }

    public function failed(User $user, AssistantActionProposal $proposal, string $reason): void
    {
        $this->log($user, $proposal, 'assistant_action_failed', ['reason' => $reason]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(User $user, AssistantActionProposal $proposal, string $event, array $context = []): void
    {
        $record = new AssistantActionEvent([
            'user_id' => $user->id,
            'assistant_action_proposal_id' => $proposal->id,
            'event' => $event,
            'action' => $proposal->action,
            'context' => $context,
            'created_at' => now(),
        ]);
        $record->company_id = $user->company_id;
        $record->save();
    }
}
