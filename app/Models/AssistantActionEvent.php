<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit trail for the assistant's action-proposal lifecycle
 * (assistant_action_proposed/confirmed/cancelled/expired/failed). See
 * AssistantActionAuditLogger — never given a raw token, a secret, or an
 * API key to store in `context`.
 */
class AssistantActionEvent extends Model
{
    use BelongsToCompany;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'assistant_action_proposal_id',
        'event',
        'action',
        'context',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
