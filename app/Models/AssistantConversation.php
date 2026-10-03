<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistantConversation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'user_id',
        'invoice_id',
        'context',
        'title',
        'status',
        'provider',
        'model_identifier',
        'screen_context',
        'started_at',
        'completed_at',
        'messages',
    ];

    protected function casts(): array
    {
        return [
            'messages' => 'array',
            'screen_context' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function assistantMessages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(AssistantMetric::class);
    }
}
