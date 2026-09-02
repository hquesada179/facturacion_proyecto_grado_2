<?php

namespace App\Services\Assistant;

use App\Contracts\AssistantProvider;

class PrototypeAssistantProvider implements AssistantProvider
{
    public function reply(array $context, string $message): array
    {
        return [
            'message' => 'Respuesta local de prototipo. La integración con un proveedor de IA externo queda desacoplada para una fase posterior.',
            'requires_confirmation' => str_contains(strtolower($message), 'finalizar') || str_contains(strtolower($message), 'anular'),
            'context' => $context,
        ];
    }
}
