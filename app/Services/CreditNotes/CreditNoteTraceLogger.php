<?php

namespace App\Services\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Models\InvoiceEvent;
use App\Services\Invoices\InvoiceTraceLogger;

class CreditNoteTraceLogger
{
    public function __construct(private readonly InvoiceTraceLogger $logger) {}

    public function log(
        CreditNote $creditNote,
        string $type,
        string $description,
        ?CreditNoteStatus $from = null,
        ?CreditNoteStatus $to = null,
        array $metadata = [],
    ): InvoiceEvent {
        return $this->logger->log(
            $creditNote->invoice,
            $type,
            $description,
            null,
            null,
            array_merge([
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNote->number,
                'credit_note_from_status' => $from?->value,
                'credit_note_to_status' => $to?->value,
            ], $metadata),
        );
    }
}
