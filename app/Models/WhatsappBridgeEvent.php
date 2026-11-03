<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit trail for the VYA bridge — see
 * WhatsappBridgeAuditLogger for the fixed set of events this ever
 * records, and the migration's own comment for why company_id/user_id
 * are nullable and (wa_id, message_id) is unique.
 */
class WhatsappBridgeEvent extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'user_id',
        'wa_id',
        'message_id',
        'event',
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
