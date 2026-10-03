<?php

namespace App\Services\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Brick\Math\BigDecimal;

class CreditNoteValidationService
{
    /**
     * @return array<string, string>
     */
    public function validateDraft(CreditNote $creditNote): array
    {
        $errors = [];
        $creditNote->loadMissing(['invoice.items', 'items']);

        if (! in_array($creditNote->invoice->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyCredited], true)) {
            $errors['invoice'] = 'La factura origen debe estar emitida o parcialmente acreditada.';
        }

        if (! $creditNote->reason_code || ! array_key_exists($creditNote->reason_code, CreditNote::REASONS)) {
            $errors['reason_code'] = 'El motivo de la nota crédito es obligatorio.';
        }

        if (! $creditNote->reason_text) {
            $errors['reason_text'] = 'La justificación de la nota crédito es obligatoria.';
        }

        if ($creditNote->items->isEmpty()) {
            $errors['items'] = 'La nota crédito debe tener al menos una línea.';
        }

        foreach ($creditNote->items as $item) {
            $available = BigDecimal::of($this->availableQuantity($item->invoiceItem, $creditNote));
            $quantity = BigDecimal::of((string) $item->credited_quantity);

            if ($quantity->isLessThanOrEqualTo(BigDecimal::zero())) {
                $errors["item_{$item->id}"] = 'La cantidad acreditada debe ser mayor que cero.';
            }

            if ($quantity->isGreaterThan($available)) {
                $errors["item_{$item->id}"] = 'La cantidad acreditada supera el saldo disponible de la línea original.';
            }

            if (BigDecimal::of((string) $item->line_total)->isGreaterThan(BigDecimal::of((string) $item->invoiceItem->line_total))) {
                $errors["item_value_{$item->id}"] = 'El valor acreditado supera el valor original de la línea.';
            }
        }

        if ($creditNote->reason_code === 'PROTO_TOTAL_VOID' && ! $this->coversAllRemainingBalance($creditNote)) {
            $errors['total_void'] = 'La anulación total debe acreditar todo el saldo restante de la factura.';
        }

        return $errors;
    }

    public function availableQuantity(InvoiceItem $invoiceItem, ?CreditNote $excludingCreditNote = null): string
    {
        $credited = CreditNoteItem::query()
            ->where('invoice_item_id', $invoiceItem->id)
            ->whereHas('creditNote', function ($query) use ($excludingCreditNote): void {
                $query->where('status', CreditNoteStatus::Issued->value);

                if ($excludingCreditNote !== null) {
                    $query->where('id', '!=', $excludingCreditNote->id);
                }
            })
            ->get()
            ->reduce(
                fn (BigDecimal $carry, CreditNoteItem $item): BigDecimal => $carry->plus((string) $item->credited_quantity),
                BigDecimal::zero(),
            );

        $available = BigDecimal::of((string) $invoiceItem->quantity)->minus($credited);

        return (string) $available;
    }

    public function invoiceIsFullyCredited(Invoice $invoice): bool
    {
        $invoice->loadMissing('items');

        foreach ($invoice->items as $item) {
            if (BigDecimal::of($this->availableQuantity($item))->isGreaterThan(BigDecimal::zero())) {
                return false;
            }
        }

        return true;
    }

    private function coversAllRemainingBalance(CreditNote $creditNote): bool
    {
        $creditNote->loadMissing(['invoice.items', 'items']);

        foreach ($creditNote->invoice->items as $invoiceItem) {
            $available = BigDecimal::of($this->availableQuantity($invoiceItem, $creditNote));
            $selected = $creditNote->items
                ->where('invoice_item_id', $invoiceItem->id)
                ->reduce(
                    fn (BigDecimal $carry, CreditNoteItem $item): BigDecimal => $carry->plus((string) $item->credited_quantity),
                    BigDecimal::zero(),
                );

            if (! $selected->isEqualTo($available)) {
                return false;
            }
        }

        return true;
    }
}
