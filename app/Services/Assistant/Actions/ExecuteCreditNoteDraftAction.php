<?php

namespace App\Services\Assistant\Actions;

use App\Models\AssistantActionProposal;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CreditNotes\CreditNoteDraftService;
use App\Services\CreditNotes\CreditNoteValidationService;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Confirm-time execution for `create_credit_note_draft`. Re-checks the
 * invoice is still eligible and, critically, that the acreditable
 * balance per line has not shrunk since the preview was shown (another
 * credit note could have been issued meanwhile) before delegating to
 * the real CreditNoteDraftService — the exact same domain path the
 * manual "Nueva nota crédito" screen uses. Only ever creates a Draft:
 * never calls validate() or issue().
 */
class ExecuteCreditNoteDraftAction
{
    public function __construct(
        private readonly CreditNoteValidationService $availability,
        private readonly CreditNoteDraftService $draftService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, AssistantActionProposal $proposal): array
    {
        $payload = $proposal->payload;

        $invoice = Invoice::withoutGlobalScopes()
            ->with('items.itemTaxes')
            ->where('company_id', $user->company_id)
            ->whereKey($payload['invoice_id'] ?? null)
            ->first();

        if ($invoice === null || Gate::forUser($user)->denies('create', [CreditNote::class, $invoice])) {
            throw ValidationException::withMessages([
                'invoice' => 'La información cambió desde que se preparó la acción. Revisa una nueva vista previa.',
            ]);
        }

        foreach ($payload['items'] ?? [] as $itemId => $line) {
            $item = $invoice->items->firstWhere('id', (int) $itemId);

            if ($item === null) {
                throw ValidationException::withMessages([
                    'items' => 'La información cambió desde que se preparó la acción. Revisa una nueva vista previa.',
                ]);
            }

            $freshAvailable = BigDecimal::of($this->availability->availableQuantity($item));
            $requested = BigDecimal::of((string) $line['quantity']);

            if ($requested->isGreaterThan($freshAvailable)) {
                throw ValidationException::withMessages([
                    'items' => 'La información cambió desde que se preparó la acción. Revisa una nueva vista previa.',
                ]);
            }
        }

        $creditNote = $this->draftService->create($user, $invoice, [
            'invoice_id' => $invoice->id,
            'reason_code' => $payload['reason_code'],
            'reason_text' => $payload['reason_text'],
            'items' => $payload['items'] ?? [],
        ]);

        return [
            'resource_type' => 'credit_note',
            'resource_id' => $creditNote->id,
            'number' => $creditNote->number,
            'invoice_number' => $invoice->number,
            'status' => $creditNote->status->value,
            'status_label' => $creditNote->status->label(),
            'total' => (float) $creditNote->total,
        ];
    }
}
