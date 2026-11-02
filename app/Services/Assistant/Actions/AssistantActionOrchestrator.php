<?php

namespace App\Services\Assistant\Actions;

use App\Models\AssistantConversation;
use App\Models\User;
use App\Services\Assistant\AssistantActionService;
use App\Services\Assistant\AssistantResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * IA 2.0-D entry point: "la IA interpreta y prepara" — resolves which
 * action (if any) a message is about, keeps the small multi-turn
 * "pending_action" draft (separate from ConversationMemory's read-only
 * context — see the migration) across turns until either a full
 * proposal is built or the user abandons it, and hands the actual
 * create/execute work to ActionProposalStore and the Propose-/Execute-
 * prefixed action classes. Never writes to the database itself.
 *
 * Interruption policy (IA 2.0-D, bug #2 fix): a pending_action is
 * PAUSED, never silently consumed, by a message that doesn't actually
 * look like a continuation of it. AiAssistantService::handle() is
 * responsible for the actual ordering — see its docblock — but the
 * rule this class enforces is: a bare "2 camisas"-shaped message, an
 * explicit "mejor N", an explicit resume phrase, or a genuine missing
 * field answered are the ONLY things that consume/advance a pending
 * action. Anything that the real read-only dispatcher recognizes on
 * its own (a count question, "busca FV-000003", "¿cuánto IVA...?",
 * ...) is handled as a normal read turn and the pending action is left
 * completely untouched, still available until its own TTL or an
 * explicit cancel — never auto-cancelled just because the user asked
 * something else in between.
 */
class AssistantActionOrchestrator
{
    private const INVOICE_VERBS = ['prepárame', 'prepara', 'créame', 'crea', 'crear'];

    private const CUSTOMER_VERBS = ['prepara', 'préparame', 'créame', 'crea', 'crear', 'registra', 'regístrame'];

    private const PRODUCT_VERBS = ['prepara', 'préparame', 'créame', 'crea', 'crear', 'registra', 'regístrame'];

    private const RESUME_PHRASES = ['continuemos', 'sigamos', 'retomemos', 'continuar', 'retomar', 'sigue', 'continúa'];

    public function __construct(
        private readonly AssistantActionInterpreter $interpreter,
        private readonly ProposeInvoiceDraftAction $invoiceDraft,
        private readonly ProposeCustomerAction $customer,
        private readonly ProposeProductAction $product,
        private readonly ProposeCreditNoteDraftAction $creditNoteDraft,
        private readonly ActionProposalStore $store,
        private readonly AssistantActionService $actionService,
    ) {}

    /**
     * Step 1 of the priority order: chat-text "confirmar"/"cancelar"
     * against a LIVE (token-bearing) pending proposal. Always checked
     * first and independent of everything else — see
     * AiAssistantService::handle().
     *
     * @param  array<string, mixed>  $pendingAction
     * @return array{response: AssistantResponse, pending_action: ?array<string, mixed>}|null
     */
    public function resolveChatShortcut(User $user, string $lower, array $pendingAction): ?array
    {
        $token = $pendingAction['token'] ?? null;

        if ($token === null) {
            return null;
        }

        if (preg_match('/^(confirmar|confirmo|s[ií],?\s*confirmar?)\.?$/u', trim($lower))) {
            try {
                $response = $this->actionService->confirm($user, $token);
            } catch (ValidationException $exception) {
                return [
                    'response' => new AssistantResponse($exception->validator->errors()->first()),
                    'pending_action' => null,
                ];
            } catch (AuthorizationException) {
                return [
                    'response' => new AssistantResponse('No tienes permiso para confirmar esta acción.'),
                    'pending_action' => null,
                ];
            }

            return ['response' => $response, 'pending_action' => null];
        }

        if (preg_match('/^(cancelar|cancelo)\.?$/u', trim($lower))) {
            try {
                $this->store->cancel($user, $token);
            } catch (ValidationException) {
                // already gone — nothing to clean up either way.
            }

            return [
                'response' => new AssistantResponse('Propuesta cancelada. Aún no se ha realizado ningún cambio.'),
                'pending_action' => null,
            ];
        }

        return null;
    }

    /**
     * Step 2: a brand-new, explicit action trigger — e.g. "Prepara un
     * cliente..." typed while an unrelated invoice-draft pending action
     * exists. Strong and unambiguous enough to always win immediately;
     * a different-typed pending action is replaced (never merged into),
     * a same-typed one is continued/merged with the fresh message.
     */
    public function detectIntent(string $lower): ?string
    {
        if ($this->hasAnyVerb($lower, ['quiero devolver', 'devolver']) && Str::contains($lower, 'factura')) {
            return 'create_credit_note_draft';
        }

        if (Str::contains($lower, ['nota crédito', 'nota credito'])
            && $this->hasAnyVerb($lower, ['prepárame', 'prepara', 'préparame', 'créame', 'crea', 'crear', 'quiero'])
        ) {
            return 'create_credit_note_draft';
        }

        if (Str::contains($lower, 'cliente') && $this->hasAnyVerb($lower, self::CUSTOMER_VERBS)) {
            return 'create_customer';
        }

        if (Str::contains($lower, ['producto', 'servicio']) && $this->hasAnyVerb($lower, self::PRODUCT_VERBS)) {
            return 'create_product';
        }

        if (Str::contains($lower, 'factura') && $this->hasAnyVerb($lower, self::INVOICE_VERBS)) {
            return 'create_invoice_draft';
        }

        if (preg_match('/^factura\s+\d+/iu', trim($lower))) {
            return 'create_invoice_draft';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $pendingAction
     * @return array{response: AssistantResponse, pending_action: ?array<string, mixed>}
     */
    public function handleExplicitIntent(User $user, string $message, AssistantConversation $conversation, array $pendingAction, string $detectedAction): array
    {
        // A different-typed pending action is abandoned (not merged)
        // the moment a new, unrelated, explicit intent is typed — the
        // same "no mezcles" rule IA 2.0-C already applied to read-only
        // conversational references.
        if (($pendingAction['action'] ?? null) !== $detectedAction) {
            $pendingAction = [];
        }

        return $this->dispatch($user, $message, $conversation, $detectedAction, $pendingAction);
    }

    /**
     * Step 5 (after the real read-only dispatcher came back empty):
     * the message is genuinely not recognizable as anything else, and
     * a pending action is still mid-collection (no proposal built yet)
     * — try interpreting it as the missing piece.
     *
     * @param  array<string, mixed>  $pendingAction
     * @return array{response: AssistantResponse, pending_action: ?array<string, mixed>}|null
     */
    public function continuePending(User $user, string $message, AssistantConversation $conversation, array $pendingAction): ?array
    {
        $action = $pendingAction['action'] ?? null;

        if ($action === null || isset($pendingAction['token'])) {
            return null;
        }

        return $this->dispatch($user, $message, $conversation, $action, $pendingAction);
    }

    /**
     * "Mejor 3" right after an invoice-draft preview was shown: cancel
     * the stale proposal and rebuild it with the corrected quantity —
     * never mutate a persisted proposal in place. Checked before the
     * read-only probe since a bare number/"mejor N" is never a
     * meaningful read query anyway.
     *
     * @param  array<string, mixed>  $pendingAction
     * @return array{response: AssistantResponse, pending_action: ?array<string, mixed>}|null
     */
    public function resolveQuantityTweak(User $user, string $lower, AssistantConversation $conversation, array $pendingAction): ?array
    {
        if (($pendingAction['action'] ?? null) !== 'create_invoice_draft' || ! isset($pendingAction['token'])) {
            return null;
        }

        $numberWord = 'un|una|uno|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez';

        if (! preg_match('/^(?:mejor\s+)?(\d+|'.$numberWord.')\s*\.?$/iu', trim($lower), $matches)) {
            return null;
        }

        $collected = $pendingAction['collected'] ?? null;

        if ($collected === null || count($collected['lines'] ?? []) !== 1) {
            return null;
        }

        try {
            $this->store->cancel($user, $pendingAction['token']);
        } catch (ValidationException) {
            return null;
        }

        $words = ['un' => '1', 'una' => '1', 'uno' => '1', 'dos' => '2', 'tres' => '3', 'cuatro' => '4', 'cinco' => '5', 'seis' => '6', 'siete' => '7', 'ocho' => '8', 'nueve' => '9', 'diez' => '10'];
        $quantity = is_numeric($matches[1]) ? $matches[1] : ($words[mb_strtolower($matches[1])] ?? '1');

        $collected['lines'][0]['quantity'] = $quantity;

        $result = $this->invoiceDraft->handle($user, $collected);

        return $this->respondToProposalResult($user, $conversation, 'create_invoice_draft', $collected, $result);
    }

    /**
     * Step 5b (after the real read-only dispatcher came back empty, no
     * quantity tweak, and the pending action already has a built
     * proposal — i.e. nothing left to "fill in"): an explicit resume
     * phrase ("continuemos con la factura") redisplays that proposal's
     * last preview, read-only — it is never reconstructed/recomputed,
     * so this can never show stale numbers as if they were fresh.
     *
     * @param  array<string, mixed>  $pendingAction
     * @return array{response: AssistantResponse, pending_action: ?array<string, mixed>}|null
     */
    public function resumeLiveProposal(User $user, string $lower, array $pendingAction): ?array
    {
        $token = $pendingAction['token'] ?? null;

        if ($token === null || ! $this->looksLikeResumePhrase($lower)) {
            return null;
        }

        $proposal = $this->store->peek($user, $token);

        if ($proposal === null) {
            return [
                'response' => new AssistantResponse('Esa propuesta ya no está disponible. Si quieres, prepárala de nuevo.'),
                'pending_action' => null,
            ];
        }

        $component = array_merge($proposal->preview, [
            'type' => 'action_proposal',
            'action' => $proposal->action,
            'token' => $token,
            'expires_at' => $proposal->expires_at->toIso8601String(),
            'note' => 'Aún no se ha realizado ningún cambio.',
        ]);

        return [
            'response' => new AssistantResponse(
                message: 'Seguimos con esta propuesta pendiente:',
                components: [$component],
                requiresConfirmation: true,
            ),
            'pending_action' => $pendingAction,
        ];
    }

    private function looksLikeResumePhrase(string $lower): bool
    {
        foreach (self::RESUME_PHRASES as $phrase) {
            if (preg_match('/(?:^|\s)'.preg_quote($phrase, '/').'\b/iu', $lower)) {
                return true;
            }
        }

        return false;
    }

    private function hasAnyVerb(string $lower, array $verbs): bool
    {
        foreach ($verbs as $verb) {
            if (preg_match('/(?:^|\s)'.preg_quote($verb, '/').'\b/iu', $lower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $pendingAction
     * @return array{response: AssistantResponse, pending_action: ?array<string, mixed>}
     */
    private function dispatch(User $user, string $message, AssistantConversation $conversation, string $action, array $pendingAction): array
    {
        return match ($action) {
            'create_invoice_draft' => $this->handleInvoiceDraft($user, $message, $conversation, $pendingAction),
            'create_customer' => $this->handleCustomer($user, $message, $conversation, $pendingAction),
            'create_product' => $this->handleProduct($user, $message, $conversation, $pendingAction),
            'create_credit_note_draft' => $this->handleCreditNoteDraft($user, $message, $conversation, $pendingAction),
            default => ['response' => new AssistantResponse('No pude procesar esa acción.'), 'pending_action' => null],
        };
    }

    // --- Invoice draft -------------------------------------------------------

    private function handleInvoiceDraft(User $user, string $message, AssistantConversation $conversation, array $pending): array
    {
        $intent = $this->interpreter->interpretInvoiceDraft($message);
        $collected = $this->mergeInvoiceIntent($pending['collected'] ?? [], $intent);

        $result = $this->invoiceDraft->handle($user, $collected);

        return $this->respondToProposalResult($user, $conversation, 'create_invoice_draft', $collected, $result);
    }

    private function mergeInvoiceIntent(array $old, array $new): array
    {
        return [
            'customer_term' => $new['customer_term'] ?? $old['customer_term'] ?? null,
            'lines' => $new['lines'] !== [] ? $new['lines'] : ($old['lines'] ?? []),
            'payment_type' => $new['payment_type'] ?? $old['payment_type'] ?? null,
            'payment_method' => $new['payment_method'] ?? $old['payment_method'] ?? null,
            'notes' => $new['notes'] ?? $old['notes'] ?? null,
        ];
    }

    // --- Customer --------------------------------------------------------------

    private function handleCustomer(User $user, string $message, AssistantConversation $conversation, array $pending): array
    {
        $intent = $this->interpreter->interpretCustomer($message);
        $collected = [
            'name' => $intent['name'] ?? $pending['collected']['name'] ?? null,
            'identification_type' => $intent['identification_type'] ?? $pending['collected']['identification_type'] ?? null,
            'identification_number' => $intent['identification_number'] ?? $pending['collected']['identification_number'] ?? null,
            'person_type' => $intent['person_type'] ?? $pending['collected']['person_type'] ?? null,
        ];

        $result = $this->customer->handle($user, $collected);

        return $this->respondToProposalResult($user, $conversation, 'create_customer', $collected, $result);
    }

    // --- Product -----------------------------------------------------------

    private function handleProduct(User $user, string $message, AssistantConversation $conversation, array $pending): array
    {
        $intent = $this->interpreter->interpretProduct($message);
        $collected = [
            'name' => $intent['name'] ?? $pending['collected']['name'] ?? null,
            'price' => $intent['price'] ?? $pending['collected']['price'] ?? null,
            'tax_rate' => $intent['tax_rate'] ?? $pending['collected']['tax_rate'] ?? null,
            'exempt' => $intent['exempt'] || ($pending['collected']['exempt'] ?? false),
            'type' => $intent['type'] ?? $pending['collected']['type'] ?? null,
        ];

        $result = $this->product->handle($user, $collected);

        return $this->respondToProposalResult($user, $conversation, 'create_product', $collected, $result);
    }

    // --- Credit note draft ---------------------------------------------------

    private function handleCreditNoteDraft(User $user, string $message, AssistantConversation $conversation, array $pending): array
    {
        $intent = $this->interpreter->interpretCreditNoteDraft($message);
        $collected = [
            'invoice_number' => $intent['invoice_number'] ?? $pending['collected']['invoice_number'] ?? null,
            'total' => $intent['total'] || ($pending['collected']['total'] ?? false),
            'lines' => $intent['lines'] !== [] ? $intent['lines'] : ($pending['collected']['lines'] ?? []),
            'bare_quantity' => $intent['bare_quantity'] ?? $pending['collected']['bare_quantity'] ?? null,
        ];

        $result = $this->creditNoteDraft->handle($user, $collected);

        return $this->respondToProposalResult($user, $conversation, 'create_credit_note_draft', $collected, $result);
    }

    // --- Shared result -> response/pending_action shaping -------------------

    /**
     * @param  array<string, mixed>  $collected
     * @param  array<string, mixed>  $result
     * @return array{response: AssistantResponse, pending_action: ?array<string, mixed>}
     */
    private function respondToProposalResult(User $user, AssistantConversation $conversation, string $action, array $collected, array $result): array
    {
        return match ($result['status']) {
            'denied' => [
                'response' => new AssistantResponse($result['reason']),
                'pending_action' => null,
            ],
            'missing' => [
                'response' => new AssistantResponse($result['reason']),
                'pending_action' => ['action' => $action, 'collected' => $collected],
            ],
            'invalid' => [
                'response' => new AssistantResponse($result['reason']),
                'pending_action' => ['action' => $action, 'collected' => $collected],
            ],
            'ambiguous' => [
                'response' => new AssistantResponse(
                    'Encontré más de una coincidencia para "'.$result['field'].'". ¿A cuál te refieres? Escribe el nombre completo.',
                    components: [[
                        'type' => $result['field'] === 'producto' ? 'product_list' : 'customer_list',
                        'items' => $result['candidates'],
                        'total_matches' => count($result['candidates']),
                        'showing' => count($result['candidates']),
                    ]],
                ),
                'pending_action' => ['action' => $action, 'collected' => $collected],
            ],
            'ready' => $this->createProposal($user, $conversation, $action, $collected, $result),
            default => [
                'response' => new AssistantResponse('No pude procesar esa acción.'),
                'pending_action' => null,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $collected
     * @param  array<string, mixed>  $result
     * @return array{response: AssistantResponse, pending_action: array<string, mixed>}
     */
    private function createProposal(User $user, AssistantConversation $conversation, string $action, array $collected, array $result): array
    {
        $created = $this->store->create($user, $conversation, $action, $result['payload'], $result['preview']);
        $proposal = $created['proposal'];
        $token = $created['token'];

        $component = array_merge($result['preview'], [
            'type' => 'action_proposal',
            'action' => $action,
            'token' => $token,
            'expires_at' => $proposal->expires_at->toIso8601String(),
            'note' => 'Aún no se ha realizado ningún cambio.',
        ]);

        return [
            'response' => new AssistantResponse(
                message: $result['preview']['summary'] ?? 'Preparé esta acción. Revisa la vista previa antes de confirmar.',
                components: [$component],
                requiresConfirmation: true,
            ),
            'pending_action' => ['action' => $action, 'token' => $token, 'collected' => $collected],
        ];
    }
}
