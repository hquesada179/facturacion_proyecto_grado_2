<?php

namespace App\Services\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use Illuminate\Support\Facades\Auth;

/**
 * The only place that writes to the append-only invoice_events log.
 */
class InvoiceTraceLogger
{
    public function log(
        Invoice $invoice,
        string $type,
        string $description,
        ?InvoiceStatus $from = null,
        ?InvoiceStatus $to = null,
        array $metadata = [],
        ?int $userId = null,
        bool $useAuthenticatedUser = true,
    ): InvoiceEvent {
        return InvoiceEvent::create([
            'invoice_id' => $invoice->id,
            'user_id' => $useAuthenticatedUser ? Auth::id() : $userId,
            'type' => $type,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
