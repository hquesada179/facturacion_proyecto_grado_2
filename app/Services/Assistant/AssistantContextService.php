<?php

namespace App\Services\Assistant;

class AssistantContextService
{
    public function forScreen(string $screen, array $metadata = []): array
    {
        return [
            'screen' => $screen,
            'metadata' => $metadata,
            'capabilities' => [
                'answer_questions',
                'explain_validation_errors',
                'suggest_invoice_corrections',
                'summarize_traceability',
                'guide_credit_notes',
            ],
            'critical_actions_require_confirmation' => true,
        ];
    }
}
