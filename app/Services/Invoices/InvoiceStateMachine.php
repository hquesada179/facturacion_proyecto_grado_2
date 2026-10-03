<?php

namespace App\Services\Invoices;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvalidInvoiceTransitionException;
use App\Models\Invoice;

/**
 * Centralizes the invoice lifecycle graph so no controller/service can
 * push an invoice into an arbitrary status. `voided` and
 * `partially_credited` are valid destinations from `issued` so the graph
 * is complete, even though nothing in this phase (no anulación / nota
 * crédito UI yet) actually triggers them.
 */
class InvoiceStateMachine
{
    private const TRANSITIONS = [
        'draft' => ['locally_validated', 'discarded'],
        'locally_validated' => ['draft', 'sending_simulated'],
        'sending_simulated' => ['issued', 'simulated_rejected', 'technical_error'],
        'simulated_rejected' => ['draft'],
        'technical_error' => ['draft'],
        'issued' => ['voided', 'partially_credited'],
        'voided' => [],
        'partially_credited' => ['voided'],
        'discarded' => [],
    ];

    /**
     * Statuses from which the customer/items/payment data may still be
     * edited. Anything else (sending_simulated, issued, voided,
     * partially_credited, discarded) is locked.
     */
    private const EDITABLE = ['draft', 'locally_validated', 'simulated_rejected', 'technical_error'];

    public static function canTransition(InvoiceStatus $from, InvoiceStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    /**
     * Validates and mutates $invoice->status in memory only — the caller
     * decides when to persist (often alongside other fields in the same
     * save()) and is responsible for recording the trace event.
     */
    public static function transition(Invoice $invoice, InvoiceStatus $to): void
    {
        if (! self::canTransition($invoice->status, $to)) {
            throw new InvalidInvoiceTransitionException(
                "No se puede pasar de '{$invoice->status->value}' a '{$to->value}'."
            );
        }

        $invoice->status = $to;
    }

    public static function isEditable(InvoiceStatus $status): bool
    {
        return in_array($status->value, self::EDITABLE, true);
    }

    public static function isTerminal(InvoiceStatus $status): bool
    {
        return (self::TRANSITIONS[$status->value] ?? []) === [];
    }
}
