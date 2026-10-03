<?php

namespace App\Services\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Exceptions\InvalidInvoiceTransitionException;
use App\Models\CreditNote;

class CreditNoteStateMachine
{
    private const TRANSITIONS = [
        'draft' => ['locally_validated', 'discarded'],
        'locally_validated' => ['draft', 'sending_simulated'],
        'sending_simulated' => ['issued', 'simulated_rejected', 'technical_error'],
        'simulated_rejected' => ['draft'],
        'technical_error' => ['draft'],
        'issued' => [],
        'discarded' => [],
    ];

    private const EDITABLE = ['draft', 'locally_validated', 'simulated_rejected', 'technical_error'];

    public static function canTransition(CreditNoteStatus $from, CreditNoteStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public static function transition(CreditNote $creditNote, CreditNoteStatus $to): void
    {
        if (! self::canTransition($creditNote->status, $to)) {
            throw new InvalidInvoiceTransitionException(
                "No se puede pasar la nota crédito de '{$creditNote->status->value}' a '{$to->value}'."
            );
        }

        $creditNote->status = $to;
    }

    public static function isEditable(CreditNoteStatus $status): bool
    {
        return in_array($status->value, self::EDITABLE, true);
    }
}
