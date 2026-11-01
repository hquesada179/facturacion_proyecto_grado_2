<?php

namespace App\Services\Assistant;

final readonly class AssistantResponse
{
    /**
     * @param  array<int, array<string, mixed>>  $suggestions
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<string, mixed>  $metadata
     * @param  array<int, array<string, mixed>>  $components  Structured, renderable
     *   items (invoice/customer/product cards, metrics, etc.) alongside the
     *   text message. Always [] in IA 2.0-A — no tool populates it yet; the
     *   field exists so the frontend and API contract are ready before any
     *   producer is implemented.
     */
    public function __construct(
        public string $message,
        public array $suggestions = [],
        public array $actions = [],
        public array $metadata = [],
        public bool $requiresConfirmation = false,
        public array $components = [],
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
            'components' => $this->components,
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
        ];
    }
}
