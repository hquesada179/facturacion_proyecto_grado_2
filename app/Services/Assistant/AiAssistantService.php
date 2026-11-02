<?php

namespace App\Services\Assistant;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantMetric;
use App\Models\Invoice;
use App\Models\ProductService;
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
use App\Services\Assistant\Tools\GetRankingTool;
use App\Services\Assistant\Actions\AssistantActionOrchestrator;
use App\Services\Assistant\Tools\GetTraceabilityTool;
use App\Services\Assistant\Tools\SearchCreditNotesTool;
use App\Services\Reports\ReportDateRange;
use App\Support\ReportFormatter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

class AiAssistantService
{
    /**
     * Invoice-status words recognised in a global, plural search such as
     * "Muéstrame las facturas anuladas" or "Muéstrame borradores". Kept
     * separate from the credit-note map below — the same word (e.g.
     * "emitidas") means a different enum depending on which document
     * type the sentence is about, and each map is only ever consulted
     * from its own branch of the dispatcher.
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
        'neto después', 'neto despues', 'iva generado', 'cuánto iva', 'cuanto iva',
        'ingresos del mes', 'ingresos',
    ];

    private const COUNT_TRIGGER_WORDS = ['cuántas', 'cuantas', 'cuántos', 'cuantos', 'cuánto', 'cuanto'];

    private const COUNT_SUBJECT_WORDS = [
        'factura', 'facturas', 'cliente', 'clientes', 'producto', 'productos',
        'nota crédito', 'notas crédito', 'nota credito', 'notas credito',
        'documento', 'documentos',
    ];

    /**
     * Explicit, bounded triggers for "that invoice we were just talking
     * about" — a demonstrative phrase ("esa factura") or a pronoun tied
     * to the specific verb of the credit-note-origin question ("la
     * anuló"). Deliberately NOT a generic pronoun detector: each entry is
     * a concrete, distinctive phrase, never a bare "la"/"lo"/"esta" that
     * would misfire on ordinary Spanish grammar.
     *
     * @var array<int, string>
     */
    private const INVOICE_REFERENCE_TRIGGERS = [
        'esa factura', 'esta factura', 'dicha factura', 'ese documento', 'este documento', 'la misma factura',
        'la anuló', 'lo anuló', 'quién la anuló', 'quien la anulo', 'qué la anuló', 'que la anulo',
    ];

    /**
     * "¿Cuáles están anuladas?" right after "facturas de kata" — only
     * ever used to decide whether to additionally scope a status search
     * to the customer remembered from the previous turn; never used to
     * resolve an entity on its own.
     *
     * @var array<int, string>
     */
    private const REFERENCE_WORDS = ['cuáles', 'cuales', 'esas', 'esos', 'de esas', 'de esos', 'la anterior', 'el anterior', 'las anteriores'];

    private const SUPERLATIVE_MAX_WORDS = ['más caro', 'mas caro', 'más cara', 'mas cara', 'más costoso', 'mas costoso', 'el más caro', 'la más cara'];

    private const SUPERLATIVE_MIN_WORDS = ['más barato', 'mas barato', 'más económico', 'mas economico', 'menos costoso', 'el más barato', 'la más barata'];

    private const RANKING_CUSTOMER_WORDS = [
        'qué cliente tiene más', 'que cliente tiene mas', 'cliente con más facturación', 'cliente con mas facturacion',
        'cliente que más ha facturado', 'cliente que mas ha facturado',
    ];

    private const RANKING_PRODUCT_WORDS = [
        'qué producto se ha facturado más', 'que producto se ha facturado mas', 'producto más facturado',
        'producto mas facturado', 'producto que más se ha facturado', 'producto que mas se ha facturado',
    ];

    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly LocalFallbackProvider $fallbackProvider,
        private readonly AssistantContextBuilder $contextBuilder,
        private readonly AssistantActionService $actions,
        private readonly AssistantComponentBuilder $components,
        private readonly AssistantMessageNormalizer $normalizer,
        private readonly AssistantDateRangeResolver $dateRangeResolver,
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
        private readonly GetRankingTool $ranking,
        private readonly AssistantActionOrchestrator $actionOrchestrator,
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
        $memory = ConversationMemory::fromArray($conversation->conversation_memory);
        $pendingAction = $conversation->pending_action ?? [];

        $this->storeMessage($conversation, 'user', $message, [
            'screen' => $screen,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]);

        $status = 'resolved';
        $errorMessage = null;
        $toolResults = [];
        $criticalActions = [];
        $lower = Str::lower($message);

        // IA 2.0-D action proposals are resolved entirely deterministically
        // — see AssistantActionOrchestrator — and never reach the AI
        // provider: the model never gets to phrase, let alone influence,
        // the text describing a pending write action.
        //
        // Priority order (bug #2 fix — a pending action must never
        // "hijack" an unrelated later message):
        //   1. confirmar/cancelar a LIVE proposal (chat-text shortcut);
        //   2. a brand-new, explicit action trigger ("Prepara un cliente...");
        //   3. "mejor N" against a live proposal;
        //   4. the REAL read-only dispatcher, tried BEFORE falling back to
        //      "this must be a continuation" — a message the dispatcher
        //      genuinely recognizes (a count, "busca FV-...", "¿cuánto
        //      IVA...?", ...) is always a normal read turn, period;
        //   5. only once that comes back empty: a missing-field answer for
        //      a still-incomplete pending action, or an explicit resume
        //      phrase ("continuemos con la factura") for a built-but-
        //      unconfirmed one;
        //   6. otherwise, the normal fallback/clarification flow.
        $actionTurn = $this->actionOrchestrator->resolveChatShortcut($user, $lower, $pendingAction);

        if ($actionTurn === null) {
            $explicitAction = $this->actionOrchestrator->detectIntent($lower);

            if ($explicitAction !== null) {
                $actionTurn = $this->actionOrchestrator->handleExplicitIntent($user, $message, $conversation, $pendingAction, $explicitAction);
            } else {
                $actionTurn = $this->actionOrchestrator->resolveQuantityTweak($user, $lower, $conversation, $pendingAction);
            }
        }

        if ($actionTurn === null) {
            $toolResults = $this->toolResults($user, $message, $context, $memory);
            $criticalActions = $this->criticalActions($user, $message, $context);
            $readTurnMatched = $toolResults !== [] || $criticalActions !== [];

            if (! $readTurnMatched && $pendingAction !== []) {
                $actionTurn = $this->actionOrchestrator->continuePending($user, $message, $conversation, $pendingAction)
                    ?? $this->actionOrchestrator->resumeLiveProposal($user, $lower, $pendingAction);
            }
        }

        if ($actionTurn !== null) {
            $response = $actionTurn['response'];
            $newPendingAction = $actionTurn['pending_action'];
            $newMemory = $memory;
            $toolResults = [];
        } else {
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

            // Structured components are always derived straight from the
            // tool results that were actually fetched under this user's
            // own authorization — never from the AI provider's text, so a
            // failed or unconfigured provider never changes what gets
            // rendered here.
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

            $newMemory = $this->memoryFromToolResults($toolResults, $memory);
            // Interruption policy, option A: a normal read-only turn
            // PAUSES any still-incomplete/still-pending action rather than
            // cancelling it — it stays available (subject to its own TTL
            // and the usual user/company/conversation checks) until the
            // user explicitly resumes, confirms, or cancels it.
            $newPendingAction = $pendingAction === [] ? null : $pendingAction;
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
            'conversation_memory' => $newMemory->toArray(),
            'pending_action' => $newPendingAction,
        ])->save();

        $this->storeMetric($user, $conversation, $assistantMessage, $screen, $status, $latencyMs, $toolResults, $errorMessage);

        return $response->toArray($conversation->id, $assistantMessage->id);
    }

    public function confirm(User $user, string $token): array
    {
        return $this->actions->confirm($user, $token)->toArray();
    }

    public function cancel(User $user, string $token): array
    {
        return $this->actions->cancel($user, $token)->toArray();
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
     * Thin wrapper over three confidence tiers, each strictly weaker than
     * the last:
     *
     *  1. canonicalized message, as typed — if this resolves to a real
     *     tool call, it is used as-is, uncorrected.
     *  2. safe typo correction (distance <= 1, unique vocabulary match)
     *     — only tried once tier 1 found nothing, and only its result is
     *     used if that retry actually resolved to something.
     *  3. low-confidence typo *suggestion* (distance == 2) — only reached
     *     once tiers 1 and 2 have BOTH come back empty. Crucially, this
     *     tier never dispatches a tool call with the guessed word: a
     *     message this garbled must never come back looking like "I
     *     understood you and found zero results" (that's a real,
     *     dispatched query's answer, tier 1/2's job) when what actually
     *     happened is "I'm not confident what word you meant" (this
     *     tier's job) — so it returns a `clarification_needed` tool
     *     result instead, same shape LocalFallbackProvider/OpenAiProvider
     *     already render for a demonstrative reference with no memory.
     *
     * If none of the three tiers find anything, the (empty) tier-1
     * result is returned unchanged — the existing, honest "I don't
     * understand, here's what you can ask me" fallback.
     *
     * @return array<int, array<string, mixed>>
     */
    private function toolResults(User $user, string $message, array $context, ConversationMemory $memory): array
    {
        $canonical = $this->normalizer->canonicalize($message);
        $results = $this->resolveToolResults($user, $canonical, $context, $memory);

        if ($results !== []) {
            return $results;
        }

        $corrected = $this->normalizer->correctVocabulary($canonical);

        if ($corrected !== null) {
            $retried = $this->resolveToolResults($user, $corrected, $context, $memory);

            if ($retried !== []) {
                return $retried;
            }
        }

        $suggestion = $this->normalizer->suggestVocabularyCorrection($canonical);

        if ($suggestion !== null) {
            return [[
                'tool' => 'clarification_needed',
                'reason' => "No estoy del todo seguro de haber entendido una palabra de tu mensaje. ¿Te refieres a «{$suggestion}»? Intenta de nuevo con esa palabra para que pueda ayudarte con certeza.",
            ]];
        }

        return $results;
    }

    /**
     * Deterministic intent resolution. Priority, in order: (1) an explicit
     * document number/customer reference the user typed this turn; (2)
     * the current screen's local context (invoice/credit_note/customer
     * already loaded); (3) a conversational reference to the previous
     * turn's entity/customer/results (ConversationMemory), only when the
     * message contains an explicit referencing phrase; (4) a global,
     * company-wide search. Kept as small, independent `if`s rather than
     * a generic rule engine or NLU model — see the IA 2.0-C report for
     * why a deterministic dispatcher was chosen over function-calling.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveToolResults(User $user, string $message, array $context, ConversationMemory $memory): array
    {
        $lower = Str::lower($message);
        $invoiceId = data_get($context, 'invoice.id');
        $creditNoteId = data_get($context, 'credit_note.id');
        $customerId = data_get($context, 'customer.id');
        $invoiceNumber = $this->extractDocumentNumber($message, 'FV');
        $creditNoteNumber = $this->extractDocumentNumber($message, 'NC');
        $dateRange = $this->dateRangeResolver->resolve($lower);
        // Any way the user named a customer explicitly THIS turn, whether
        // "facturas de kata" or "¿cuántas facturas tiene kata?" — both
        // phrasings are handled by the same combined-filter resolver.
        $customerTerm = $this->extractCustomerDocumentsTerm($message)
            ?? $this->extractAfter($message, ['tiene', 'tienen', 'tuvo', 'tuvieron']);
        $isCountQuestion = $this->containsWholeWord($lower, self::COUNT_TRIGGER_WORDS)
            && $this->containsWholeWord($lower, self::COUNT_SUBJECT_WORDS);
        $hasReferenceTrigger = Str::contains($lower, self::INVOICE_REFERENCE_TRIGGERS) || Str::contains($lower, self::REFERENCE_WORDS);
        $hasSuperlativeTrigger = Str::contains($lower, self::SUPERLATIVE_MAX_WORDS) || Str::contains($lower, self::SUPERLATIVE_MIN_WORDS);

        // An explicit FV-number always wins; otherwise, a demonstrative
        // reference ("esa factura", "la anuló") falls back to the invoice
        // remembered from the previous turn — never guessed when memory
        // is empty or stale (see hasFreshEntity()).
        $referencedNumber = $invoiceNumber;

        if ($referencedNumber === null && Str::contains($lower, self::INVOICE_REFERENCE_TRIGGERS) && $memory->hasFreshEntity('invoice')) {
            $referencedNumber = $memory->entityNumber;
        }

        $results = [];

        // --- Local-context explanations (unchanged from earlier phases) ---

        if ($invoiceId && Str::contains($lower, ['validación', 'validacion', 'error', 'bloqueo', 'no puedo emitir', 'por qué no puedo'])) {
            $results[] = $this->explainValidation->handle($user, (int) $invoiceId);
        }

        if ($invoiceId && Str::contains($lower, ['iva', 'impuesto', 'total', 'calcular', 'calculó', 'calculo', 'acreditada'])) {
            $results[] = $this->invoiceDetails->handle($user, (int) $invoiceId);
        }

        // --- Traceability: explicit/remembered number wins over local context ---

        if (($invoiceId || $referencedNumber) && Str::contains($lower, ['trazabilidad', 'qué pasó', 'que paso', 'historial', 'resume la trazabilidad'])) {
            $results[] = $this->traceability->handle(
                $user,
                $referencedNumber ? null : (int) $invoiceId,
                $referencedNumber,
            );
        }

        // --- Invoice search: explicit number > customer-of > status/period filters ---

        if ($invoiceNumber !== null) {
            $results[] = $this->findInvoice->handle($user, $invoiceNumber);
        } elseif (Str::contains($lower, 'factura') && $invoiceId && $customerTerm === null) {
            $results[] = $this->invoiceDetails->handle($user, (int) $invoiceId);
        } elseif ($customerTerm === null
            && ! Str::contains($lower, self::INVOICE_REFERENCE_TRIGGERS)
            && ! Str::contains($lower, ['pendiente', 'pendientes'])
        ) {
            // A singular, demonstrative phrase like "esta factura está
            // parcialmente acreditada" is a question about one
            // already-known document (handled by the local-context
            // branches above), never a global list request. "Borradores
            // PENDIENTES" is its own, more specific branch below (which
            // also covers credit notes) — skip the unscoped status
            // search here so the two don't both answer the same message.
            $invoiceStatus = $this->detectStatusConfident($message, $lower, self::INVOICE_STATUS_WORDS);

            // A date phrase alone ("facturas este mes", no status word
            // at all) legitimately means "every status, that period" —
            // but if there's ALSO a word here that's a plausible-but-
            // unsafe typo of a status word (e.g. "barradires", distance
            // 2 from "borradores"), silently dropping it and returning
            // every invoice that period would answer a different,
            // broader question than the one actually asked while
            // looking just as confident. Ask instead of guessing.
            if ($invoiceStatus === null && $dateRange !== null) {
                $ambiguousStatusWord = $this->ambiguousStatusWord($message, self::INVOICE_STATUS_WORDS);

                if ($ambiguousStatusWord !== null) {
                    return [[
                        'tool' => 'clarification_needed',
                        'reason' => "No estoy del todo seguro de haber entendido una palabra de tu mensaje. ¿Te refieres a los «{$ambiguousStatusWord}»? Intenta de nuevo con esa palabra para que pueda ayudarte con certeza.",
                    ]];
                }
            }

            if ($invoiceStatus !== null || $dateRange !== null) {
                $filters = ['status' => $invoiceStatus];

                if ($dateRange !== null) {
                    $filters['date_from'] = $dateRange->start->toDateTimeString();
                    $filters['date_to'] = $dateRange->end->toDateTimeString();
                }

                $memoryCustomerId = null;
                $memoryCustomerName = null;

                // "¿Cuáles están anuladas?" right after "facturas de
                // kata" — no customer named this turn, but an explicit
                // referencing word plus a fresh remembered customer
                // means "of the same customer as before", not everyone.
                if ($invoiceStatus !== null && Str::contains($lower, self::REFERENCE_WORDS) && $memory->hasFreshCustomer()) {
                    $filters['customer_id'] = $memory->customerId;
                    $memoryCustomerId = $memory->customerId;
                    $memoryCustomerName = $memory->customerName;
                }

                $result = $this->findInvoice->handle($user, null, array_filter($filters), 10);

                if ($memoryCustomerId !== null) {
                    $result['_customer_id'] = $memoryCustomerId;
                    $result['_customer_name'] = $memoryCustomerName;
                }

                $results[] = $result;
            }
        }

        // --- Customer / product free search ---

        if (Str::contains($lower, ['cliente', 'clientes']) && $customerTerm === null) {
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

        if ($hasSuperlativeTrigger) {
            $direction = Str::contains($lower, self::SUPERLATIVE_MAX_WORDS) ? 'desc' : 'asc';
            $superlative = $this->resolveSuperlativeFromMemory($user, $memory, $direction);

            if ($superlative !== null) {
                $results[] = $superlative;
            }
        }

        // --- Credit notes: single lookup > by origin invoice > by customer > general ---

        if ($creditNoteId && Str::contains($lower, ['nota crédito', 'nota credito', 'acredit'])) {
            $results[] = $this->creditNoteDetails->handle($user, (int) $creditNoteId);
        } elseif ($creditNoteNumber !== null) {
            $results[] = $this->creditNoteDetails->handle($user, number: $creditNoteNumber);
        } elseif ($referencedNumber !== null && Str::contains($lower, self::CREDIT_NOTE_WORDS)) {
            $results[] = $this->searchCreditNotes->handle($user, ['invoice_number' => $referencedNumber]);
        } elseif ($customerTerm !== null && Str::contains($lower, self::CREDIT_NOTE_WORDS)) {
            // Covered by the customer-scoped branch below, which returns
            // the right document type depending on what was asked.
        } elseif (Str::contains($lower, self::CREDIT_NOTE_WORDS)) {
            $status = $this->detectStatus($lower, self::CREDIT_NOTE_STATUS_WORDS);
            $results[] = $this->searchCreditNotes->handle($user, array_filter(['status' => $status]));
        }

        // --- "Facturas/notas crédito/documentos de|tiene|tuvo <cliente>"
        // (global, count or list, with optional status/date filters) or
        // the customer-detail quick action (local context by id) ---

        if ($customerId && Str::contains($lower, ['documento', 'documentos', 'factura', 'facturas', ...self::CREDIT_NOTE_WORDS])) {
            $results[] = $this->customerInvoices->handle($user, customerId: (int) $customerId);
        } elseif ($customerTerm !== null) {
            $results[] = $this->resolveCustomerScopedDocuments($user, $customerTerm, $lower, $dateRange);
        }

        // --- Global counts / errors / pending / billing / ranking — never tied to a screen ---

        if ($isCountQuestion && $customerTerm === null) {
            $results[] = $this->documentCounts->handle($user);
        }

        if ($invoiceNumber === null && Str::contains($lower, ['error técnico', 'error tecnico', 'qué errores', 'que errores', 'rechazos', 'documentos con error'])) {
            $results[] = $this->documentErrors->handle($user);
        }

        if (Str::contains($lower, ['pendiente', 'pendientes'])) {
            $results[] = $this->pendingDocuments->handle($user);
        }

        if (Str::contains($lower, self::BILLING_KEYWORDS)) {
            $results[] = $this->billingSummary->handle($user, $dateRange);
        }

        if (Str::contains($lower, self::RANKING_CUSTOMER_WORDS)) {
            $results[] = $this->ranking->handle($user, 'customers', $dateRange);
        } elseif (Str::contains($lower, self::RANKING_PRODUCT_WORDS)) {
            $results[] = $this->ranking->handle($user, 'products', $dateRange);
        }

        $results = collect($results)
            ->unique(fn (array $result): string => ($result['tool'] ?? 'unknown').':'.md5(json_encode($result)))
            ->values()
            ->all();

        // Never invent an entity: if the message clearly referenced
        // "that" invoice/result but nothing above could resolve it (no
        // fresh memory), ask instead of guessing.
        if ($results === [] && ($hasReferenceTrigger || $hasSuperlativeTrigger)) {
            return [[
                'tool' => 'clarification_needed',
                'reason' => 'No tengo claro a qué te refieres. ¿Puedes darme el número de factura, el nombre del cliente o repetir la búsqueda?',
            ]];
        }

        return $results;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function criticalActions(User $user, string $message, array $context): array
    {
        $lower = Str::lower($message);

        if (! Str::contains($lower, [
            'emitir', 'anular', 'eliminar', 'desactivar', 'cambiar cliente', 'modificar importe', 'modificar total',
            'modificar resolución', 'modificar resolucion', 'modificar impuesto', 'crear usuario', 'cambiar rol',
            'cambiar seguridad', 'enviar correo', 'enviar un correo',
        ])) {
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

        // Creating an invoice draft/customer/product/credit-note draft is
        // now a real IA 2.0-D action proposal (see
        // AssistantActionOrchestrator), handled before this method is
        // even reached — only genuinely destructive/elevating intents
        // (emitir, anular, eliminar, modificar seguridad/impuestos/
        // numeración, crear usuarios...) ever land here, and none of
        // those are available from the assistant in any phase so far.
        return [[
            'requires_confirmation' => true,
            'action' => 'critical_action_not_available',
            'summary' => 'Esta acción crítica no se ejecuta desde una respuesta textual del asistente.',
        ]];
    }

    /**
     * Shared resolver for every "<documento(s)> de/tiene/tuvo <cliente>"
     * phrasing — count questions and plain list questions alike. Resolves
     * the customer once via GetCustomerInvoicesTool::resolveCustomer()
     * (never guesses between two customers with the same name), then
     * combines whatever status/date filters were detected in the same
     * message and delegates to the same search tools used everywhere
     * else, so the number/list is always a real total_matches, never
     * invented or recalculated separately.
     */
    private function resolveCustomerScopedDocuments(User $user, string $term, string $lower, ?ReportDateRange $dateRange): array
    {
        $resolution = $this->customerInvoices->resolveCustomer($user, $term);

        if ($resolution['status'] !== 'found') {
            return [
                'tool' => 'customer_documents',
                'found' => false,
                'ambiguous' => $resolution['status'] === 'ambiguous',
                'candidates' => $resolution['candidates'] ?? [],
            ];
        }

        $customer = $resolution['customer'];
        $tag = ['_customer_id' => $customer->id, '_customer_name' => $customer->name];
        $wantsCreditNotes = Str::contains($lower, self::CREDIT_NOTE_WORDS);
        $wantsInvoices = Str::contains($lower, ['factura', 'facturas']);

        if ($wantsCreditNotes && ! $wantsInvoices) {
            $creditStatus = $this->detectStatus($lower, self::CREDIT_NOTE_STATUS_WORDS);

            return $this->searchCreditNotes->handle($user, array_filter(['customer_id' => $customer->id, 'status' => $creditStatus])) + $tag;
        }

        if ($wantsInvoices && ! $wantsCreditNotes) {
            $status = $this->detectStatus($lower, self::INVOICE_STATUS_WORDS);
            $filters = ['customer_id' => $customer->id, 'status' => $status];

            if ($dateRange !== null) {
                $filters['date_from'] = $dateRange->start->toDateTimeString();
                $filters['date_to'] = $dateRange->end->toDateTimeString();
            }

            return $this->findInvoice->handle($user, null, array_filter($filters), 10) + $tag;
        }

        // Generic "documentos" (or both mentioned at once): combined view.
        return $this->customerInvoices->handle($user, customerId: $customer->id);
    }

    /**
     * "¿Cuál es el más caro/barato?" right after a product search — never
     * re-uses cached data: re-fetches those exact ids, fresh, re-checking
     * company_id and the view policy per item, same as any other tool.
     */
    private function resolveSuperlativeFromMemory(User $user, ConversationMemory $memory, string $direction): ?array
    {
        $ids = $memory->freshResultIds('product');

        if ($ids === []) {
            return null;
        }

        $products = ProductService::withoutGlobalScopes()
            ->with('taxes')
            ->where('company_id', $user->company_id)
            ->whereIn('id', $ids)
            ->get()
            ->filter(fn (ProductService $product): bool => Gate::forUser($user)->allows('view', $product));

        if ($products->isEmpty()) {
            return null;
        }

        $top = $direction === 'desc'
            ? $products->sortByDesc(fn (ProductService $product): float => (float) $product->price)->first()
            : $products->sortBy(fn (ProductService $product): float => (float) $product->price)->first();

        return [
            'tool' => 'find_product',
            'total_matches' => 1,
            'matches' => [[
                'id' => $top->id,
                'sku' => $top->sku,
                'name' => $top->name,
                'price' => (float) $top->price,
                'status' => $top->status->label(),
                'taxes' => $top->taxes->map(fn ($tax): string => $tax->code.' '.$tax->rate.'%')->implode(', ') ?: 'Sin impuestos asociados',
            ]],
        ];
    }

    private function extractDocumentNumber(string $message, string $prefix): ?string
    {
        preg_match('/\b'.$prefix.'-\d{1,12}\b/i', $message, $matches);

        return isset($matches[0]) ? strtoupper($matches[0]) : null;
    }

    /**
     * "facturas de kata" / "facturas anuladas de kata" / "notas crédito
     * emitidas de kata" — allows up to 3 words (a status adjective, a
     * date phrase) between the document noun and "de", lazily, so the
     * nearest "de" is always the one used. The captured term still runs
     * through cleanResidualTerm() to strip a trailing date phrase.
     */
    private function extractCustomerDocumentsTerm(string $message): ?string
    {
        if (preg_match('/\b(?:facturas?|notas?\s+cr[eé]dito|documentos?)\b(?:\s+\w+){0,3}?\s+de\s+(.+)/iu', $message, $matches)) {
            return $this->cleanResidualTerm(trim($matches[1], " \t\n\r\0\x0B.,;:!?¿¡"));
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
     * "inactivo", a trailing date phrase) out of a free-text term so a
     * query like "productos con IVA 19%" or "facturas anuladas de kata
     * este mes" doesn't also try to LIKE-match those literal words
     * against a customer/product name.
     */
    private function cleanResidualTerm(string $term): ?string
    {
        $term = preg_replace('/iva\s*\d+(?:[.,]\d+)?\s*%?/iu', '', $term) ?? $term;
        $term = preg_replace('/\b(con|activo|activos|inactivo|inactivos)\b/iu', '', $term) ?? $term;
        $term = preg_replace(
            '/\b(este mes|esta semana|últimos?\s*7\s*días|ultimos?\s*7\s*dias|últimos?\s*30\s*días|ultimos?\s*30\s*dias|mes pasado|este año|este ano|hoy|ayer)\b/iu',
            '',
            $term
        ) ?? $term;
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

    /**
     * Same as detectStatus(), plus one safe fallback: if no status word
     * matched exactly, retry against a distance<=1 vocabulary correction
     * of the whole message — e.g. "borradpres" still confidently means
     * "borradores" even though this word was never dispatched with on
     * its own (see AssistantMessageNormalizer::correctVocabulary()).
     *
     * @param  array<string, \UnitEnum&\BackedEnum>  $map
     */
    private function detectStatusConfident(string $message, string $lower, array $map): ?string
    {
        $exact = $this->detectStatus($lower, $map);

        if ($exact !== null) {
            return $exact;
        }

        $corrected = $this->normalizer->correctVocabulary($message);

        return $corrected !== null ? $this->detectStatus(Str::lower($corrected), $map) : null;
    }

    /**
     * A distance==2 guess (AssistantMessageNormalizer::
     * suggestVocabularyCorrection()) that happens to land on one of
     * THIS status map's own words — used only to decide whether to ask
     * for clarification instead of silently treating the word as if it
     * was never there; the guessed word is never dispatched against.
     *
     * @param  array<string, \UnitEnum&\BackedEnum>  $map
     */
    private function ambiguousStatusWord(string $message, array $map): ?string
    {
        $suggestion = $this->normalizer->suggestVocabularyCorrection($message);

        return ($suggestion !== null && array_key_exists($suggestion, $map)) ? $suggestion : null;
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

    /**
     * Builds the small structured snapshot the NEXT message will read to
     * resolve references like "esa factura" or "cuáles". A turn that
     * didn't touch any specific entity (e.g. a billing/count question)
     * keeps the previous memory alive rather than wiping it — only a new,
     * concrete, entity-bearing result ever replaces it.
     */
    private function memoryFromToolResults(array $toolResults, ConversationMemory $previous): ConversationMemory
    {
        foreach ($toolResults as $result) {
            $built = $this->memoryFromSingleResult($result);

            if ($built !== null) {
                return $built;
            }
        }

        return $previous;
    }

    private function memoryFromSingleResult(array $result): ?ConversationMemory
    {
        return match ($result['tool'] ?? null) {
            'find_invoice' => $this->memoryFromInvoiceMatches($result),
            'invoice_details' => isset($result['invoice']['id']) ? ConversationMemory::record(
                entityType: 'invoice',
                entityId: $result['invoice']['id'],
                entityNumber: $result['invoice']['number'] ?? null,
                queryType: 'invoice_details',
            ) : null,
            'traceability' => ($result['found'] ?? false) ? ConversationMemory::record(
                entityType: 'invoice',
                entityNumber: $result['invoice_number'] ?? null,
                queryType: 'traceability',
            ) : null,
            'credit_note_details' => ($result['found'] ?? false) ? ConversationMemory::record(
                entityType: 'credit_note',
                entityId: $result['credit_note']['id'] ?? null,
                entityNumber: $result['credit_note']['number'] ?? null,
                queryType: 'credit_note_details',
            ) : null,
            'find_product' => $this->memoryFromProductMatches($result),
            'find_customer' => $this->memoryFromCustomerMatches($result),
            'customer_documents' => $this->memoryFromCustomerDocuments($result),
            default => null,
        };
    }

    private function memoryFromInvoiceMatches(array $result): ?ConversationMemory
    {
        $matches = $result['matches'] ?? [];

        if ($matches === []) {
            return null;
        }

        $resultIds = array_column($matches, 'id');
        $customerId = $result['_customer_id'] ?? null;
        $customerName = $result['_customer_name'] ?? null;

        return ConversationMemory::record(
            entityType: 'invoice',
            entityId: count($matches) === 1 ? $matches[0]['id'] : null,
            entityNumber: count($matches) === 1 ? $matches[0]['number'] : null,
            customerId: $customerId,
            customerName: $customerName,
            queryType: 'invoice_search',
            resultIds: $resultIds,
        );
    }

    private function memoryFromProductMatches(array $result): ?ConversationMemory
    {
        $matches = $result['matches'] ?? [];

        if ($matches === []) {
            return null;
        }

        return ConversationMemory::record(
            entityType: 'product',
            entityId: count($matches) === 1 ? $matches[0]['id'] : null,
            queryType: 'product_search',
            resultIds: array_column($matches, 'id'),
        );
    }

    private function memoryFromCustomerMatches(array $result): ?ConversationMemory
    {
        $matches = $result['matches'] ?? [];

        if (count($matches) !== 1) {
            return null;
        }

        return ConversationMemory::record(
            entityType: 'customer',
            customerId: $matches[0]['id'],
            customerName: $matches[0]['name'],
            queryType: 'customer_search',
        );
    }

    private function memoryFromCustomerDocuments(array $result): ?ConversationMemory
    {
        if (! ($result['found'] ?? false)) {
            return null;
        }

        $invoiceIds = array_column($result['invoices'] ?? [], 'id');
        $creditNoteIds = array_column($result['credit_notes'] ?? [], 'id');

        return ConversationMemory::record(
            entityType: 'customer',
            customerId: $result['customer']['id'] ?? null,
            customerName: $result['customer']['name'] ?? null,
            queryType: 'customer_documents',
            resultIds: $invoiceIds !== [] ? $invoiceIds : $creditNoteIds,
        );
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
