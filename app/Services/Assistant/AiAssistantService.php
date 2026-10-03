<?php

namespace App\Services\Assistant;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantMetric;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Assistant\Contracts\AiProviderInterface;
use App\Services\Assistant\Providers\LocalFallbackProvider;
use App\Services\Assistant\Tools\ExplainValidationTool;
use App\Services\Assistant\Tools\FindCustomerTool;
use App\Services\Assistant\Tools\FindInvoiceTool;
use App\Services\Assistant\Tools\FindProductTool;
use App\Services\Assistant\Tools\GetCreditNoteDetailsTool;
use App\Services\Assistant\Tools\GetInvoiceDetailsTool;
use App\Services\Assistant\Tools\GetTraceabilityTool;
use App\Support\ReportFormatter;
use Illuminate\Support\Str;
use Throwable;

class AiAssistantService
{
    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly LocalFallbackProvider $fallbackProvider,
        private readonly AssistantContextBuilder $contextBuilder,
        private readonly AssistantActionService $actions,
        private readonly FindInvoiceTool $findInvoice,
        private readonly GetInvoiceDetailsTool $invoiceDetails,
        private readonly ExplainValidationTool $explainValidation,
        private readonly GetTraceabilityTool $traceability,
        private readonly FindCustomerTool $findCustomer,
        private readonly FindProductTool $findProduct,
        private readonly GetCreditNoteDetailsTool $creditNoteDetails,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handle(User $user, array $payload): array
    {
        $started = microtime(true);
        $message = trim((string) $payload['message']);
        $screen = (string) ($payload['screen'] ?? 'unknown');
        $resourceType = $payload['resource_type'] ?? null;
        $resourceId = isset($payload['resource_id']) ? (int) $payload['resource_id'] : null;
        $context = $this->contextBuilder->build($user, $screen, $resourceType, $resourceId);
        $conversation = $this->conversationFor($user, $payload, $context, $message);

        $this->storeMessage($conversation, 'user', $message, [
            'screen' => $screen,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]);

        $toolResults = $this->toolResults($user, $message, $context);
        $criticalActions = $this->criticalActions($user, $message, $context);
        $status = 'resolved';
        $errorMessage = null;

        try {
            $response = $this->provider->generate($context, $message, $toolResults);
        } catch (Throwable $throwable) {
            $status = 'error';
            $errorMessage = $throwable->getMessage();
            $response = new AssistantResponse(
                'El asistente no está disponible temporalmente. Las funciones de facturación continúan funcionando normalmente.',
                metadata: ['provider_error' => true],
            );
        }

        if ($this->provider instanceof LocalFallbackProvider && empty($toolResults)) {
            $response = $this->fallbackProvider->generate($context, $message, $toolResults);
        }

        if ($criticalActions !== []) {
            $response = new AssistantResponse(
                message: $response->message."\n\nPreparé la acción como crítica. Requiere confirmación humana explícita antes de ejecutarse.",
                suggestions: $response->suggestions,
                actions: $criticalActions,
                metadata: $response->metadata,
                requiresConfirmation: true,
            );
        }

        $assistantMessage = $this->storeMessage($conversation, 'assistant', $response->message, [
            'suggestions' => $response->suggestions,
            'actions' => $this->actionsWithoutTokens($response->actions),
            'provider' => $this->provider->name(),
            'model_identifier' => $this->provider->modelIdentifier(),
            'tool_names' => $this->toolNames($toolResults),
        ]);

        $latencyMs = (int) round((microtime(true) - $started) * 1000);

        $conversation->forceFill([
            'status' => $status === 'resolved' ? 'resolved' : 'open',
            'provider' => $this->provider->name(),
            'model_identifier' => $this->provider->modelIdentifier(),
            'screen_context' => $this->contextSummary($context),
            'completed_at' => now(),
            'messages' => $this->appendLegacyMessages($conversation, $message, $response->message),
        ])->save();

        $this->storeMetric($user, $conversation, $assistantMessage, $screen, $status, $latencyMs, $toolResults, $errorMessage);

        return $response->toArray($conversation->id, $assistantMessage->id);
    }

    public function confirm(User $user, string $token): array
    {
        return $this->actions->confirm($user, $token)->toArray();
    }

    private function conversationFor(User $user, array $payload, array $context, string $message): AssistantConversation
    {
        if (! empty($payload['conversation_id'])) {
            $conversation = AssistantConversation::withoutGlobalScopes()
                ->where('company_id', $user->company_id)
                ->where('user_id', $user->id)
                ->whereKey((int) $payload['conversation_id'])
                ->first();

            if ($conversation) {
                return $conversation;
            }
        }

        $conversation = new AssistantConversation([
            'user_id' => $user->id,
            'invoice_id' => data_get($context, 'invoice.id'),
            'context' => (string) ($context['screen'] ?? 'unknown'),
            'title' => Str::limit($message, 80),
            'status' => 'open',
            'provider' => $this->provider->name(),
            'model_identifier' => $this->provider->modelIdentifier(),
            'screen_context' => $this->contextSummary($context),
            'started_at' => now(),
            'messages' => [],
        ]);
        $conversation->company_id = $user->company_id;
        $conversation->save();

        return $conversation;
    }

    private function storeMessage(AssistantConversation $conversation, string $role, string $content, array $metadata = []): AssistantMessage
    {
        return AssistantMessage::create([
            'assistant_conversation_id' => $conversation->id,
            'role' => $role,
            'content' => $content,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toolResults(User $user, string $message, array $context): array
    {
        $lower = Str::lower($message);
        $invoiceId = data_get($context, 'invoice.id');
        $creditNoteId = data_get($context, 'credit_note.id');
        $invoiceNumber = $this->extractDocumentNumber($message, 'FV');
        $creditNoteNumber = $this->extractDocumentNumber($message, 'NC');
        $results = [];

        if ($invoiceId && Str::contains($lower, ['validación', 'validacion', 'error', 'bloqueo', 'no puedo emitir', 'por qué no puedo'])) {
            $results[] = $this->explainValidation->handle($user, (int) $invoiceId);
        }

        if ($invoiceId && Str::contains($lower, ['iva', 'impuesto', 'total', 'calcular', 'calculó', 'calculo', 'acreditada'])) {
            $results[] = $this->invoiceDetails->handle($user, (int) $invoiceId);
        }

        if ($invoiceId && Str::contains($lower, ['trazabilidad', 'qué pasó', 'que paso', 'historial'])) {
            $results[] = $this->traceability->handle($user, (int) $invoiceId);
        }

        if ($invoiceNumber !== null) {
            $results[] = $this->findInvoice->handle($user, $invoiceNumber);
        } elseif (Str::contains($lower, 'factura') && $invoiceId) {
            $results[] = $this->invoiceDetails->handle($user, (int) $invoiceId);
        }

        if (Str::contains($lower, 'cliente')) {
            $results[] = $this->findCustomer->handle($user, $this->searchTerm($message, 'cliente'));
        }

        if (Str::contains($lower, ['producto', 'servicio'])) {
            $results[] = $this->findProduct->handle($user, $this->searchTerm($message, 'producto'));
        }

        if ($creditNoteId && Str::contains($lower, ['nota crédito', 'nota credito', 'acredit'])) {
            $results[] = $this->creditNoteDetails->handle($user, (int) $creditNoteId);
        } elseif ($creditNoteNumber !== null) {
            $results[] = $this->creditNoteDetails->handle($user, number: $creditNoteNumber);
        }

        return collect($results)
            ->unique(fn (array $result): string => ($result['tool'] ?? 'unknown').':'.md5(json_encode($result)))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function criticalActions(User $user, string $message, array $context): array
    {
        $lower = Str::lower($message);

        if (! Str::contains($lower, ['emitir', 'anular', 'eliminar', 'desactivar', 'cambiar cliente', 'modificar importe', 'modificar total'])) {
            return [];
        }

        if (Str::contains($lower, 'emitir') && data_get($context, 'invoice.id')) {
            $invoice = Invoice::withoutGlobalScopes()
                ->where('company_id', $user->company_id)
                ->whereKey((int) data_get($context, 'invoice.id'))
                ->first();

            if ($invoice && $user->can('update', $invoice)) {
                $label = $invoice->number ?: 'borrador #'.$invoice->id;

                return [
                    $this->actions->createConfirmation(
                        $user,
                        'issue_invoice',
                        'invoice',
                        $invoice->id,
                        'Emitir '.$label.' por '.ReportFormatter::money($invoice->total),
                    ),
                ];
            }
        }

        return [[
            'requires_confirmation' => true,
            'action' => 'critical_action_not_available',
            'summary' => 'Esta acción crítica no se ejecuta desde una respuesta textual del asistente.',
        ]];
    }

    private function extractDocumentNumber(string $message, string $prefix): ?string
    {
        preg_match('/\b'.$prefix.'-\d{1,12}\b/i', $message, $matches);

        return isset($matches[0]) ? strtoupper($matches[0]) : null;
    }

    private function searchTerm(string $message, string $anchor): string
    {
        $position = stripos($message, $anchor);

        if ($position === false) {
            return $message;
        }

        return trim(substr($message, $position + strlen($anchor)));
    }

    private function toolNames(array $toolResults): array
    {
        return collect($toolResults)
            ->pluck('tool')
            ->filter()
            ->values()
            ->all();
    }

    private function actionsWithoutTokens(array $actions): array
    {
        return collect($actions)
            ->map(function (array $action): array {
                unset($action['token']);

                return $action;
            })
            ->all();
    }

    private function contextSummary(array $context): array
    {
        return [
            'screen' => $context['screen'] ?? null,
            'user_role' => $context['user_role'] ?? null,
            'resource' => $context['resource'] ?? null,
            'shared_context_types' => array_values(array_intersect(array_keys($context), [
                'invoice',
                'credit_note',
                'customer',
                'product',
                'validation',
            ])),
        ];
    }

    private function appendLegacyMessages(AssistantConversation $conversation, string $userMessage, string $assistantMessage): array
    {
        $messages = $conversation->messages ?? [];
        $messages[] = ['role' => 'user', 'content' => $userMessage, 'created_at' => now()->toDateTimeString()];
        $messages[] = ['role' => 'assistant', 'content' => $assistantMessage, 'created_at' => now()->toDateTimeString()];

        return array_slice($messages, -20);
    }

    private function storeMetric(
        User $user,
        AssistantConversation $conversation,
        AssistantMessage $message,
        string $screen,
        string $status,
        int $latencyMs,
        array $toolResults,
        ?string $errorMessage,
    ): void {
        $metric = new AssistantMetric([
            'user_id' => $user->id,
            'assistant_conversation_id' => $conversation->id,
            'assistant_message_id' => $message->id,
            'screen' => $screen,
            'provider' => $this->provider->name(),
            'model_identifier' => $this->provider->modelIdentifier(),
            'status' => $status,
            'latency_ms' => $latencyMs,
            'tool_names' => $this->toolNames($toolResults),
            'error_message' => $errorMessage,
        ]);
        $metric->company_id = $user->company_id;
        $metric->save();
    }
}
