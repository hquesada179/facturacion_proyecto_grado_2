<?php

namespace App\Services\Assistant\Contracts;

use App\Services\Assistant\AssistantResponse;

interface AiProviderInterface
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $toolResults
     */
    public function generate(array $context, string $message, array $toolResults = []): AssistantResponse;

    public function name(): string;

    public function modelIdentifier(): string;
}
