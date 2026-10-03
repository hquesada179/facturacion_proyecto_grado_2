<?php

namespace App\Services\Assistant;

final readonly class AssistantResponse
{
    /**
     * @param  array<int, array<string, mixed>>  $suggestions
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $message,
        public array $suggestions = [],
        public array $actions = [],
        public array $metadata = [],
        public bool $requiresConfirmation = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(?int $conversationId = null, ?int $messageId = null): array
    {
        return [
            'message' => $this->message,
            'suggestions' => $this->suggestions,
            'actions' => $this->actions,
            'requires_confirmation' => $this->requiresConfirmation,
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
        ];
    }
}
