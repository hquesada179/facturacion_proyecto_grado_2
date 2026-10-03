<?php

namespace App\Services\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditNoteDraftService
{
    public function __construct(
        private readonly CreditNoteCalculator $calculator,
        private readonly CreditNoteValidationService $validator,
        private readonly CreditNoteTraceLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, Invoice $invoice, array $data): CreditNote
    {
        $invoice->loadMissing(['items.itemTaxes', 'customer']);

        if ($user->company_id !== $invoice->company_id) {
            abort(404);
        }

        if (! in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyCredited], true)) {
            throw ValidationException::withMessages([
                'invoice_id' => 'Solo se pueden crear notas crédito sobre facturas emitidas.',
            ]);
        }

        $reasonCode = (string) ($data['reason_code'] ?? '');
        $reasonText = trim((string) ($data['reason_text'] ?? ''));

        if (! array_key_exists($reasonCode, CreditNote::REASONS)) {
            throw ValidationException::withMessages(['reason_code' => 'Selecciona un motivo válido de prototipo.']);
        }

        if ($reasonText === '') {
            throw ValidationException::withMessages(['reason_text' => 'La justificación es obligatoria.']);
        }

        $selectedItems = $this->selectedItems($invoice, $data['items'] ?? []);

        if ($selectedItems === []) {
            throw ValidationException::withMessages(['items' => 'Selecciona al menos una línea para acreditar.']);
        }

        return DB::transaction(function () use ($user, $invoice, $reasonCode, $reasonText, $data, $selectedItems): CreditNote {
            $creditNote = new CreditNote([
                'invoice_id' => $invoice->id,
                'user_id' => $user->id,
                'reason' => CreditNote::REASONS[$reasonCode],
                'reason_code' => $reasonCode,
                'reason_text' => $reasonText,
                'notes' => $data['notes'] ?? null,
            ]);
            $creditNote->company_id = $invoice->company_id;
            $creditNote->save();

            foreach ($selectedItems as ['item' => $invoiceItem, 'quantity' => $quantity]) {
                $this->createItem($creditNote, $invoiceItem, $quantity);
            }

            $creditNote = $this->calculator->recalculate($creditNote);
            $errors = $this->validator->validateDraft($creditNote);

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $this->logger->log(
                $creditNote,
                'credit_note_draft_created',
                'Se creó un borrador de nota crédito de prueba.',
                null,
                CreditNoteStatus::Draft,
                ['invoice_id' => $invoice->id, 'reason_code' => $reasonCode],
            );

            foreach ($creditNote->items as $item) {
                $this->logger->log(
                    $creditNote,
                    'credit_note_item_added',
                    "Se agregó la línea acreditada \"{$item->description}\".",
                    CreditNoteStatus::Draft,
                    CreditNoteStatus::Draft,
                    ['credit_note_item_id' => $item->id, 'invoice_item_id' => $item->invoice_item_id],
                );
            }

            return $creditNote->refresh();
        });
    }

    public function validate(CreditNote $creditNote): CreditNote
    {
        $creditNote = $this->calculator->recalculate($creditNote);
        $errors = $this->validator->validateDraft($creditNote);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($creditNote->status === CreditNoteStatus::Draft) {
            CreditNoteStateMachine::transition($creditNote, CreditNoteStatus::LocallyValidated);
            $creditNote->save();
            $this->logger->log(
                $creditNote,
                'credit_note_validated',
                'La nota crédito pasó la validación local.',
                CreditNoteStatus::Draft,
                CreditNoteStatus::LocallyValidated,
            );
        }

        return $creditNote->refresh();
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @return array<int, array{item: InvoiceItem, quantity: string}>
     */
    private function selectedItems(Invoice $invoice, array $items): array
    {
        $selected = [];

        foreach ($invoice->items as $invoiceItem) {
            $raw = $items[$invoiceItem->id]['quantity'] ?? $items[$invoiceItem->id] ?? null;

            if ($raw === null || $raw === '') {
                continue;
            }

            $quantity = BigDecimal::of((string) $raw);

            if ($quantity->isEqualTo(BigDecimal::zero())) {
                continue;
            }

            $selected[] = ['item' => $invoiceItem, 'quantity' => (string) $quantity];
        }

        return $selected;
    }

    private function createItem(CreditNote $creditNote, InvoiceItem $invoiceItem, string $quantity): void
    {
        $creditNote->items()->create([
            'invoice_item_id' => $invoiceItem->id,
            'product_code' => $invoiceItem->product_code,
            'description' => $invoiceItem->description,
            'unit' => $invoiceItem->unit,
            'original_quantity' => $invoiceItem->quantity,
            'credited_quantity' => $quantity,
            'unit_price' => $invoiceItem->unit_price,
            'discount_percent' => $invoiceItem->discount_percent,
            'tax_snapshot' => $invoiceItem->itemTaxes->map(fn ($tax): array => [
                'tax_id' => $tax->tax_id,
                'code' => $tax->code,
                'name' => $tax->name,
                'rate' => (string) $tax->rate,
            ])->values()->all(),
        ]);
    }
}
