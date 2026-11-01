<?php

namespace App\Services\Assistant;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
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
use App\Services\Assistant\Tools\GetBillingSummaryTool;
use App\Services\Assistant\Tools\GetCreditNoteDetailsTool;
use App\Services\Assistant\Tools\GetCustomerInvoicesTool;
use App\Services\Assistant\Tools\GetDocumentCountsTool;
use App\Services\Assistant\Tools\GetDocumentErrorsTool;
use App\Services\Assistant\Tools\GetInvoiceDetailsTool;
use App\Services\Assistant\Tools\GetPendingDocumentsTool;
use App\Services\Assistant\Tools\GetTraceabilityTool;
use App\Services\Assistant\Tools\SearchCreditNotesTool;
use App\Services\Reports\ReportDateRange;
use App\Support\ReportFormatter;
use Illuminate\Support\Str;
use Throwable;

class AiAssistantService
{
    /**
     * Invoice-status words recognised in a global, plural search such as
     * "Muéstrame las facturas anuladas". Deliberately separate from the
     * credit-note map below — the same word (e.g. "emitidas") means a
     * different enum depending on which document type the sentence is
     * actually about, and each map is only ever consulted from its own
     * branch of the dispatcher.
     *
     * @var array<string, InvoiceStatus>
     */
    private const INVOICE_STATUS_WORDS = [
        'anuladas' => InvoiceStatus::Voided,
        'anulada' => InvoiceStatus::Voided,
        'anulado' => InvoiceStatus::Voided,
        'emitidas' => InvoiceStatus::Issued,
        'emitida' => InvoiceStatus::Issued,
        'borradores' => InvoiceStatus::Draft,
        'borrador' => InvoiceStatus::Draft,
        'parcialmente abonadas' => InvoiceStatus::PartiallyCredited,
        'parcialmente abonada' => InvoiceStatus::PartiallyCredited,
        'parcialmente acreditadas' => InvoiceStatus::PartiallyCredited,
        'parcialmente acreditada' => InvoiceStatus::PartiallyCredited,
        'error técnico' => InvoiceStatus::TechnicalError,
        'error tecnico' => InvoiceStatus::TechnicalError,
        'rechazadas' => InvoiceStatus::SimulatedRejected,
        'rechazada' => InvoiceStatus::SimulatedRejected,
    ];

    /** @var array<string, CreditNoteStatus> */
    private const CREDIT_NOTE_STATUS_WORDS = [
        'emitidas' => CreditNoteStatus::Issued,
        'emitida' => CreditNoteStatus::Issued,
        'con error' => CreditNoteStatus::TechnicalError,
        'error técnico' => CreditNoteStatus::TechnicalError,
        'error tecnico' => CreditNoteStatus::TechnicalError,
        'rechazadas' => CreditNoteStatus::SimulatedRejected,
        'rechazada' => CreditNoteStatus::SimulatedRejected,
    ];

    private const CREDIT_NOTE_WORDS = ['nota crédito', 'notas crédito', 'nota credito', 'notas credito'];

    private const BILLING_KEYWORDS = [
        'facturado', 'facturación', 'facturacion', 'neto documental',
        'neto después', 'neto despues', 'iva he generado', 'iva generado',
        'cuánto iva', 'cuanto iva', 'ingresos del mes', 'ingresos',
    ];

    private const COUNT_TRIGGER_WORDS = ['cuántas', 'cuantas', 'cuántos', 'cuantos', 'cuánto', 'cuanto'];

    private const COUNT_SUBJECT_WORDS = [
        'factura', 'facturas', 'cliente', 'clientes', 'producto', 'productos',
        'nota crédito', 'notas crédito', 'nota credito', 'notas credito',
    ];

    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly LocalFallbackProvider $fallbackProvider,
        private readonly AssistantContextBuilder $contextBuilder,
        private readonly AssistantActionService $actions,
        private readonly AssistantComponentBuilder $components,
        private readonly FindInvoiceTool $findInvoice,
        private readonly GetInvoiceDetailsTool $invoiceDetails,
        private readonly ExplainValidationTool $explainValidation,
        private readonly GetTraceabilityTool $traceability,
        private readonly FindCustomerTool $findCustomer,
        private readonly FindProductTool $findProduct,
        private readonly GetCreditNoteDetailsTool $creditNoteDetails,
        private readonly SearchCreditNotesTool $searchCreditNotes,
        private readonly GetCustomerInvoicesTool $customerInvoices,
        private readonly GetDocumentCountsTool $documentCounts,
        private readonly GetDocumentErrorsTool $documentErrors,
        private readonly GetPendingDocumentsTool $pendingDocuments,
        private readonly GetBillingSummaryTool $billingSummary,
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

        // Structured components are always derived straight from the tool
        // results that were actually fetched under this user's own
        // authorization — never from the AI provider's text, so a failed
        // or unconfigured provider never changes what gets rendered here.
        $components = $this->components->build($user, $toolResults);

        if ($criticalActions !== []) {
            $response = new AssistantResponse(
                message: $response->message."\n\nPreparé la acción como crítica. Requiere confirmación humana explícita antes de ejecutarse.",
                suggestions: $response->suggestions,
                actions: $criticalActions,
                metadata: $response->metadata,
                requiresConfirmation: true,
                components: $components,
            );
        } elseif ($components !== []) {
            $response = new AssistantResponse(
                message: $response->message,
                suggestions: $response->suggestions,
                actions: $response->actions,
                metadata: $response->metadata,
                requiresConfirmation: $response->requiresConfirmation,
                components: $components,
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
     * Deterministic intent resolution. Priority, in order: (1) an explicit
     * document number/customer reference the user typed; (2) the current
     * screen's local context (invoice/credit_note/customer already
     * loaded); (3) a global, company-wide search. Every branch below maps
     * to one or more of the example queries in the IA 2.0-B brief — kept
     * as small, independent `if`s rather than a generic rule engine, per
     * the explicit "no excessively complex system" instruction.
     *
     * @return array<int, array<string, mixed>>
     */
    private function toolResults(User $user, string $message, array $context): array
    {
        $lower = Str::lower($message);
        $invoiceId = data_get($context, 'invoice.id');
        $creditNoteId = data_get($context, 'credit_note.id');
        $customerId = data_get($context, 'customer.id');
        $invoiceNumber = $this->extractDocumentNumber($message, 'FV');
        $creditNoteNumber = $this->extractDocumentNumber($message, 'NC');
        $customerDocumentsTerm = $this->extractCustomerDocumentsTerm($message);
        $results = [];

        // --- Local-context explanations (unchanged from earlier phases) ---

        if ($invoiceId && Str::contains($lower, ['validación', 'validacion', 'error', 'bloqueo', 'no puedo emitir', 'por qué no puedo'])) {
            $results[] = $this->explainValidation->handle($user, (int) $invoiceId);
        }

        if ($invoiceId && Str::contains($lower, ['iva', 'impuesto', 'total', 'calcular', 'calculó', 'calculo', 'acreditada'])) {
            $results[] = $this->invoiceDetails->handle($user, (int) $invoiceId);
        }

        // --- Traceability: explicit FV-number always wins over local context ---

        if (($invoiceId || $invoiceNumber) && Str::contains($lower, ['trazabilidad', 'qué pasó', 'que paso', 'historial', 'resume la trazabilidad'])) {
            $results[] = $this->traceability->handle(
                $user,
                $invoiceNumber ? null : (int) $invoiceId,
                $invoiceNumber,
            );
        }

        // --- Invoice search: explicit number > customer-of > status/period filters ---

        if ($invoiceNumber !== null) {
            $results[] = $this->findInvoice->handle($user, $invoiceNumber);
        } elseif (Str::contains($lower, 'factura') && $invoiceId && $customerDocumentsTerm === null) {
            $results[] = $this->invoiceDetails->handle($user, (int) $invoiceId);
        } elseif ($customerDocumentsTerm === null && Str::contains($lower, 'facturas')) {
            // Plural "facturas" specifically — a singular, demonstrative
            // phrase like "esta factura está parcialmente acreditada" is a
            // question about one already-known document (handled by the
            // local-context branches above), never a global list request.
            $invoiceStatus = $this->detectStatus($lower, self::INVOICE_STATUS_WORDS);
            $period = $this->detectPeriod($lower);

            if ($invoiceStatus !== null || $period !== null) {
                $filters = ['status' => $invoiceStatus];

                if ($period !== null) {
                    $range = ReportDateRange::fromFilters(['date_range' => $period]);
                    $filters['date_from'] = $range->start->toDateTimeString();
                    $filters['date_to'] = $range->end->toDateTimeString();
                }

                $results[] = $this->findInvoice->handle($user, null, array_filter($filters), 10);
            }
        }

        // --- Customer / product free search ---

        if (Str::contains($lower, ['cliente', 'clientes']) && $customerDocumentsTerm === null) {
            $term = $this->cleanResidualTerm($this->extractAfter($message, ['cliente', 'clientes']) ?? '');
            $results[] = $this->findCustomer->handle($user, $term);
        }

        if (Str::contains($lower, ['producto', 'productos', 'servicio', 'servicios'])) {
            $rawTerm = $this->extractAfter($message, ['producto', 'productos', 'servicio', 'servicios']) ?? '';
            $taxRate = $this->extractTaxRate($message);
            $term = $this->cleanResidualTerm($rawTerm);
            $filters = array_filter(['tax_rate' => $taxRate]);
            $results[] = $this->findProduct->handle($user, $term, $filters);
        }

        // --- Credit notes: single lookup > by origin invoice > by customer > general ---

        if ($creditNoteId && Str::contains($lower, ['nota crédito', 'nota credito', 'acredit'])) {
            $results[] = $this->creditNoteDetails->handle($user, (int) $creditNoteId);
        } elseif ($creditNoteNumber !== null) {
            $results[] = $this->creditNoteDetails->handle($user, number: $creditNoteNumber);
        } elseif ($invoiceNumber !== null && Str::contains($lower, self::CREDIT_NOTE_WORDS)) {
            $results[] = $this->searchCreditNotes->handle($user, ['invoice_number' => $invoiceNumber]);
        } elseif ($customerDocumentsTerm !== null && Str::contains($lower, self::CREDIT_NOTE_WORDS)) {
            // Already covered by the customer_documents branch below,
            // which returns both invoices and credit notes together.
        } elseif (Str::contains($lower, self::CREDIT_NOTE_WORDS)) {
            $status = $this->detectStatus($lower, self::CREDIT_NOTE_STATUS_WORDS);
            $results[] = $this->searchCreditNotes->handle($user, array_filter(['status' => $status]));
        }

        // --- "Facturas/notas crédito de <cliente>" (global) or the
        // customer-detail quick action (local context by id) ---

        if ($customerId && Str::contains($lower, ['documento', 'documentos', 'factura', 'facturas', ...self::CREDIT_NOTE_WORDS])) {
            $results[] = $this->customerInvoices->handle($user, customerId: (int) $customerId);
        } elseif ($customerDocumentsTerm !== null) {
            $results[] = $this->customerInvoices->handle($user, term: $customerDocumentsTerm);
        }

        // --- Global counts / errors / pending / billing — never tied to a screen ---

        // Word-boundary match for the subject only: a plain substring check
        // would make "facturado"/"facturación" (billing questions) falsely
        // trigger a counts answer just because they contain "factura".
        if (Str::contains($lower, self::COUNT_TRIGGER_WORDS) && $this->containsWholeWord($lower, self::COUNT_SUBJECT_WORDS)) {
            $results[] = $this->documentCounts->handle($user);
        }

        if ($invoiceNumber === null && Str::contains($lower, ['error técnico', 'error tecnico', 'qué errores', 'que errores', 'rechazos', 'documentos con error'])) {
            $results[] = $this->documentErrors->handle($user);
        }

        if (Str::contains($lower, ['pendiente', 'pendientes'])) {
            $results[] = $this->pendingDocuments->handle($user);
        }

        if (Str::contains($lower, self::BILLING_KEYWORDS)) {
            $period = $this->detectPeriod($lower) ?? ReportDateRange::DEFAULT;
            $results[] = $this->billingSummary->handle($user, $period);
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

        if (! Str::contains($lower, ['emitir', 'anular', 'eliminar', 'desactivar', 'cambiar cliente', 'modificar importe', 'modificar total', 'crea una factura', 'crear una factura', 'crea un cliente', 'crea un producto'])) {
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

        // IA 2.0-B is read-only: creation/write intents are acknowledged
        // but never prepared or executed — that lands in a later phase.
        if (Str::contains($lower, ['crea una factura', 'crear una factura', 'crea un cliente', 'crea un producto', 'crea una nota crédito', 'crear una nota crédito'])) {
            return [[
                'requires_confirmation' => false,
                'action' => 'write_action_not_available_yet',
                'summary' => 'Puedo ayudarte a localizar al cliente, los productos o los documentos relacionados, pero la creación asistida se habilitará en una fase posterior.',
            ]];
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

    /**
     * "facturas de kata" / "notas crédito de kata" / "documentos de kata" —
     * matched as one literal pattern (rather than a generic anchor) since
     * the anchor itself is a two/three-word phrase, not a single keyword.
     */
    private function extractCustomerDocumentsTerm(string $message): ?string
    {
        if (preg_match('/\b(?:facturas?|notas?\s+cr[eé]dito|documentos?)\s+de\s+(.+)/iu', $message, $matches)) {
            $term = $this->cleanResidualTerm(trim($matches[1], " \t\n\r\0\x0B.,;:!?¿¡"));

            return $term;
        }

        return null;
    }

    /**
     * Finds the first of several word-boundary anchors (e.g. both
     * "cliente" and "clientes" must be listed explicitly — a boundary
     * match on "cliente" alone never matches inside "clientes") and
     * returns the trimmed remainder of the message after it, with a
     * leading "de"/"del"/"de la" connector stripped.
     *
     * @param  array<int, string>  $anchors
     */
    private function extractAfter(string $message, array $anchors): ?string
    {
        $pattern = '/\b(?:'.implode('|', array_map(fn (string $a): string => preg_quote($a, '/'), $anchors)).')\b/iu';

        if (! preg_match($pattern, $message, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        [$matchedText, $offset] = $matches[0];
        $rest = trim(mb_substr($message, $offset + mb_strlen($matchedText)));
        $rest = preg_replace('/^(de la|de los|de las|del|de)\s+/iu', '', $rest) ?? $rest;
        $rest = trim($rest, " \t\n\r\0\x0B.,;:!?¿¡");

        return $rest !== '' ? $rest : null;
    }

    /**
     * Strips recognised filter phrases (tax rate, "con", "activo"/
     * "inactivo") out of a free-text term so a query like "productos con
     * IVA 19%" doesn't also try to LIKE-match the literal words "con IVA
     * 19%" against product names — it should become a pure filter search
     * instead of a doomed-to-fail text search.
     */
    private function cleanResidualTerm(string $term): ?string
    {
        $term = preg_replace('/iva\s*\d+(?:[.,]\d+)?\s*%?/iu', '', $term) ?? $term;
        $term = preg_replace('/\b(con|activo|activos|inactivo|inactivos)\b/iu', '', $term) ?? $term;
        $term = trim($term, " \t\n\r\0\x0B.,;:!?¿¡");

        return $term !== '' ? $term : null;
    }

    /**
     * @param  array<int, string>  $words
     */
    private function containsWholeWord(string $lower, array $words): bool
    {
        $pattern = '/\b(?:'.implode('|', array_map(fn (string $w): string => preg_quote($w, '/'), $words)).')\b/iu';

        return (bool) preg_match($pattern, $lower);
    }

    private function extractTaxRate(string $message): ?float
    {
        if (preg_match('/iva\s*(\d+(?:[.,]\d+)?)\s*%?/iu', $message, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }

        return null;
    }

    /**
     * @param  array<string, \UnitEnum&\BackedEnum>  $map
     */
    private function detectStatus(string $lower, array $map): ?string
    {
        foreach ($map as $word => $status) {
            if (Str::contains($lower, $word)) {
                return $status->value;
            }
        }

        return null;
    }

    private function detectPeriod(string $lower): ?string
    {
        if (Str::contains($lower, ['este mes', 'del mes', 'mensual'])) {
            return 'this_month';
        }

        if (Str::contains($lower, ['esta semana', 'últimos 7 días', 'ultimos 7 dias', 'última semana', 'ultima semana'])) {
            return 'last_7_days';
        }

        if (Str::contains($lower, ['hoy', 'el día de hoy', 'el dia de hoy'])) {
            return 'today';
        }

        return null;
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
