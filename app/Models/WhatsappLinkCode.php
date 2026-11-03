<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single pending WhatsApp-linking code. Never queried from a route
 * directly — only WhatsappLinkService touches this model — and
 * `code_hash`/`lookup_hash` are hidden defensively even though nothing
 * currently serializes this model to a response.
 */
class WhatsappLinkCode extends Model
{
    protected $fillable = [
        'user_id',
        'company_id',
        'code_hash',
        'lookup_hash',
        'expires_at',
        'used_at',
    ];

    protected $hidden = ['code_hash', 'lookup_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
