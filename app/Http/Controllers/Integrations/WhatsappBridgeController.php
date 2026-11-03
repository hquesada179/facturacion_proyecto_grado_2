<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\WhatsappBridgeMessageRequest;
use App\Models\Company;
use App\Models\User;
use App\Models\WhatsappBridgeEvent;
use App\Services\Assistant\AiAssistantService;
use App\Services\Whatsapp\WhatsappBridgeAuditLogger;
use App\Services\Whatsapp\WhatsappLinkService;
use App\Services\Whatsapp\WhatsappNumberNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

/**
 * The entire Fiscora side of "WhatsApp -> VYA webhook -> this endpoint
 * -> AiAssistantService::handle() -> VYA sends the reply back". Never
 * duplicates the assistant's own logic, tools, or AI provider — every
 * non-linking message is a single, unmodified call to
 * AiAssistantService::handle($user, $payload), the exact same entry
 * point the web assistant uses (see AssistantController::message()).
 *
 * No session, no CSRF, no Auth::login() — see
 * VerifyWhatsappBridgeSignature for the HMAC authentication that
 * guards this whole route, and the AiAssistantService/Tools themselves,
 * which already take $user as a plain argument and never read the
 * global Auth facade.
 */
class WhatsappBridgeController extends Controller
{
    public function __construct(
        private readonly WhatsappLinkService $links,
        private readonly WhatsappBridgeAuditLogger $audit,
        private readonly AiAssistantService $assistant,
    ) {}

    public function handle(WhatsappBridgeMessageRequest $request): JsonResponse
    {
        $waId = WhatsappNumberNormalizer::normalize($request->validated('wa_id'));
        $phoneNumberId = $request->validated('phone_number_id');
        $message = trim((string) $request->validated('message'));
        $messageId = $request->validated('message_id');

        if ($waId === '' || $message === '') {
            return response()->json(['handled' => false]);
        }

        // Idempotency: Meta/VYA may redeliver the same webhook. If this
        // exact (wa_id, message_id) was already handled, return the
        // original result instead of linking/confirming/replying again.
        if ($messageId !== null) {
            $cached = $this->cachedResponseFor($waId, $messageId);

            if ($cached !== null) {
                return response()->json($cached);
            }
        }

        $linkCode = $this->detectLinkCommand($message);

        $result = $linkCode !== null
            ? $this->handleLinkCommand($waId, $phoneNumberId, $linkCode)
            : $this->handleAssistantMessage($waId, $message);

        $response = $this->publicResponse($result);

        $this->audit->recordBridgeOutcome(
            $waId,
            $messageId,
            $result['_user'] ?? null,
            $result['_connection'] ?? null,
            $result['type'] ?? 'unhandled',
            $response,
        );

        return response()->json($response);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cachedResponseFor(string $waId, string $messageId): ?array
    {
        $event = WhatsappBridgeEvent::where('wa_id', $waId)->where('message_id', $messageId)->first();

        if ($event === null) {
            return null;
        }

        return $event->context['response'] ?? ['handled' => true, 'type' => 'duplicate', 'reply' => ''];
    }

    private function detectLinkCommand(string $message): ?string
    {
        if (preg_match('/^\s*VINCULAR\s+FISCORA-([A-Z0-9]{4,10})\s*$/iu', $message, $matches) === 1) {
            return Str::upper($matches[1]);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function handleLinkCommand(string $waId, ?string $phoneNumberId, string $code): array
    {
        $existing = $this->links->activeConnectionFor($waId);

        if ($existing !== null) {
            $company = Company::find($existing->company_id);

            return [
                'type' => 'already_linked',
                'reply' => $company
                    ? "Este WhatsApp ya está vinculado con Fiscora.\n\nEmpresa: {$company->name}"
                    : 'Este WhatsApp ya está vinculado con Fiscora.',
                '_connection' => $existing,
            ];
        }

        $outcome = $this->links->consume($waId, $phoneNumberId, $code);

        if (! $outcome['success']) {
            return [
                'type' => 'link_error',
                'reply' => 'El código de vinculación no es válido o ya venció. Genera uno nuevo desde Fiscora.',
            ];
        }

        /** @var User $user */
        $user = $outcome['user'];
        /** @var Company $company */
        $company = $outcome['company'];

        return [
            'type' => 'linked',
            'reply' => "✅ WhatsApp vinculado con Fiscora.\n\nEmpresa: {$company->name}\n\nYa puedes consultarme información de tu empresa.",
            '_user' => $user,
            '_connection' => $outcome['connection'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleAssistantMessage(string $waId, string $message): array
    {
        $connection = $this->links->activeConnectionFor($waId);

        if ($connection === null) {
            // No Fiscora connection at all for this number — the only
            // case VYA should treat as "not mine, continue your own flow".
            return ['type' => 'unlinked', 'handled' => false];
        }

        $user = User::withoutGlobalScopes()->find($connection->user_id);

        // A connection row exists but can no longer be trusted as-is
        // (user missing, or no longer in the company it was linked
        // under — e.g. an administrative change after linking). This IS
        // a Fiscora number, so VYA must not fall back to its own flow —
        // it's "not_authorized", never silently "unlinked".
        if ($user === null || $user->company_id !== $connection->company_id || Gate::forUser($user)->denies('use-assistant')) {
            return [
                'type' => 'not_authorized',
                'reply' => 'Tu usuario de Fiscora ya no tiene acceso al asistente. Contacta a un administrador de tu empresa.',
                '_user' => $user,
                '_connection' => $connection,
            ];
        }

        try {
            $assistantResult = $this->assistant->handle($user, [
                'message' => $message,
                'screen' => 'whatsapp',
                'conversation_id' => $connection->assistant_conversation_id,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return [
                'type' => 'assistant_error',
                'reply' => 'El asistente no está disponible temporalmente. Intenta de nuevo en unos minutos.',
                '_user' => $user,
                '_connection' => $connection,
            ];
        }

        $conversationId = $assistantResult['conversation_id'] ?? null;

        if ($conversationId !== null && $conversationId !== $connection->assistant_conversation_id) {
            $connection->forceFill(['assistant_conversation_id' => $conversationId])->save();
        }

        return [
            'type' => 'assistant',
            'reply' => (string) ($assistantResult['message'] ?? ''),
            'conversation_id' => $conversationId,
            'requires_confirmation' => (bool) ($assistantResult['requires_confirmation'] ?? false),
            '_user' => $user,
            '_connection' => $connection,
        ];
    }

    /**
     * Strips internal-only keys (the resolved User/WhatsappConnection
     * instances) and shapes the final, VYA-facing JSON — never internal
     * IDs, tokens, hashes, or anything beyond plain text + the handful
     * of fields VYA's WhatsAppSupportService::sendText() flow needs.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function publicResponse(array $result): array
    {
        if (($result['type'] ?? null) === 'unlinked') {
            return ['handled' => false];
        }

        $response = [
            'handled' => true,
            'type' => $result['type'],
            'reply' => $result['reply'] ?? '',
        ];

        if (array_key_exists('conversation_id', $result)) {
            $response['conversation_id'] = $result['conversation_id'];
            $response['requires_confirmation'] = $result['requires_confirmation'] ?? false;
        }

        return $response;
    }
}
