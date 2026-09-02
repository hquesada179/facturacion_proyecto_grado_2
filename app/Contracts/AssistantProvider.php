<?php

namespace App\Contracts;

interface AssistantProvider
{
    /**
     * @return array{message: string, requires_confirmation: bool, context: array<string, mixed>}
     */
    public function reply(array $context, string $message): array;
}
