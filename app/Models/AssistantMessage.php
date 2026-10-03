<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantMessage extends Model
{
    protected $fillable = [
        'assistant_conversation_id',
        'role',
        'content',
        'metadata',
        'feedback',
        'feedback_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'feedback_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'assistant_conversation_id');
    }
}
