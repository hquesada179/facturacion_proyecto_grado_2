<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantMetric extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'user_id',
        'assistant_conversation_id',
        'assistant_message_id',
        'screen',
        'provider',
        'model_identifier',
        'status',
        'latency_ms',
        'tool_names',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'tool_names' => 'array',
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

    public function message(): BelongsTo
    {
        return $this->belongsTo(AssistantMessage::class, 'assistant_message_id');
    }
}
