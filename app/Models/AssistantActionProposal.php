<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A server-persisted, single-use proposal for an assistant action
 * awaiting explicit human confirmation. See IA 2.0-D report ("Confirmación
 * segura"): the only thing that ever identifies this row to the outside
 * world is token_hash (a sha256 of a random token returned to the client
 * exactly once); payload holds the RESOLVED identifiers the action will
 * re-verify at confirm time (never a trusted price/total — those are
 * always recomputed fresh from current data, see ActionProposalStore).
 */
class AssistantActionProposal extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'assistant_conversation_id',
        'action',
        'status',
        'token_hash',
        'payload',
        'preview',
        'result',
        'expires_at',
        'confirmed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'preview' => 'array',
            'result' => 'array',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'assistant_conversation_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
