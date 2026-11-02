<?php

namespace App\Services\Notifications;

use App\Models\AssistantActionEvent;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\User;
use App\Services\Reports\ReportQueryFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Builds the topbar bell feed entirely from existing append-only audit
 * trails (invoice_events, assistant_action_events) — no new table, no
 * persisted read/unread state (see UI/UX 2.0-C report: that's deferred,
 * tracked client-side instead). Scoping mirrors GetDocumentErrorsTool:
 * explicit company_id filtering on invoices plus the same "own documents
 * only" rule ReportQueryFactory already applies to Facturador elsewhere.
 */
class NotificationCenterService
{
    private const LIMIT = 20;

    /**
     * Only the subset of invoice_events.type values that represent an
     * outcome worth surfacing to a human (issued/rejected/voided/error).
     * Intermediate steps (issuance_requested, simulated_validation_started,
     * credit_note_draft_created, etc.) are deliberately excluded — see the
     * "no conviertas todo evento en ruido" requirement.
     */
    private const DOCUMENT_EVENT_TYPES = [
        'issued',
        'local_validation_failed',
        'simulated_validation_rejected',
        'technical_error',
        'invoice_voided',
        'invoice_partially_credited',
        'credit_note_issued',
        'credit_note_simulated_rejected',
        'credit_note_technical_error',
    ];

    private const ASSISTANT_EVENT_TYPES = [
        'assistant_action_confirmed',
        'assistant_action_failed',
    ];

    private const ASSISTANT_ACTION_LABELS = [
        'create_invoice_draft' => 'un borrador de factura',
        'create_customer' => 'un cliente',
        'create_product' => 'un producto o servicio',
        'create_credit_note_draft' => 'un borrador de nota crédito',
    ];

    public function __construct(private readonly ReportQueryFactory $queries) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forUser(User $user): array
    {
        $items = collect();

        if (Gate::forUser($user)->allows('view-reports')) {
            $items = $items->concat($this->documentEvents($user));
        }

        if (Gate::forUser($user)->allows('use-assistant')) {
            $items = $items->concat($this->assistantEvents($user));
        }

        return $items
            ->sortByDesc(fn (array $item) => $item['timestamp'])
            ->take(self::LIMIT)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function documentEvents(User $user): Collection
    {
        $invoiceIds = Invoice::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->when(
                $this->queries->mustLimitToOwnDocuments($user),
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->pluck('id');

        if ($invoiceIds->isEmpty()) {
            return collect();
        }

        return InvoiceEvent::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->whereIn('type', self::DOCUMENT_EVENT_TYPES)
            ->latest()
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (InvoiceEvent $event): array => $this->mapDocumentEvent($event));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function assistantEvents(User $user): Collection
    {
        // Assistant actions are personal to the conversation that proposed
        // them — scoped to the current user regardless of role, never to
        // the whole company, so one Facturador never sees another's drafts.
        return AssistantActionEvent::query()
            ->where('user_id', $user->id)
            ->whereIn('event', self::ASSISTANT_EVENT_TYPES)
            ->latest()
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (AssistantActionEvent $event): array => $this->mapAssistantEvent($event));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDocumentEvent(InvoiceEvent $event): array
    {
        [$tone, $icon, $title] = $this->documentPresentation($event->type);
        $creditNoteId = $event->metadata['credit_note_id'] ?? null;

        return [
            'id' => 'invoice_event_'.$event->id,
            'tone' => $tone,
            'icon' => $icon,
            'title' => $title,
            'description' => $event->description,
            'timestamp' => $event->created_at,
            'url' => $creditNoteId
                ? route('credit-notes.show', $creditNoteId)
                : route('invoices.show', $event->invoice_id),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function documentPresentation(string $type): array
    {
        return match ($type) {
            'issued' => ['success', 'description', 'Factura emitida'],
            'credit_note_issued' => ['success', 'assignment_return', 'Nota crédito emitida'],
            'invoice_voided' => ['neutral', 'cancel', 'Factura anulada'],
            'invoice_partially_credited' => ['neutral', 'undo', 'Factura parcialmente acreditada'],
            'local_validation_failed' => ['warning', 'warning', 'Validación con errores'],
            'simulated_validation_rejected' => ['warning', 'warning', 'Factura rechazada'],
            'credit_note_simulated_rejected' => ['warning', 'warning', 'Nota crédito rechazada'],
            'technical_error', 'credit_note_technical_error' => ['error', 'error', 'Error técnico'],
            default => ['neutral', 'info', 'Actualización de documento'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAssistantEvent(AssistantActionEvent $event): array
    {
        $label = self::ASSISTANT_ACTION_LABELS[$event->action] ?? 'una acción';

        if ($event->event === 'assistant_action_failed') {
            return [
                'id' => 'assistant_event_'.$event->id,
                'tone' => 'error',
                'icon' => 'auto_awesome',
                'title' => 'Acción asistida fallida',
                'description' => "El asistente no pudo completar {$label}.",
                'timestamp' => $event->created_at,
                'url' => route('assistant.index'),
            ];
        }

        return [
            'id' => 'assistant_event_'.$event->id,
            'tone' => 'success',
            'icon' => 'auto_awesome',
            'title' => 'Acción asistida completada',
            'description' => "El asistente creó {$label}.",
            'timestamp' => $event->created_at,
            'url' => route('assistant.index'),
        ];
    }
}
