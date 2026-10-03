<?php

namespace App\Services\Invoices;

use App\Models\Invoice;

/**
 * Generates a deterministic, clearly-fake "CUFE" for the simulated flow —
 * SHA-384 of the invoice's own defining fields, prefixed SIM- so nobody
 * can mistake it for a real DIAN CUFE. Never presented as real.
 */
class CufeGenerator
{
    public function generate(Invoice $invoice): string
    {
        $payload = implode('|', [
            $invoice->company->nit,
            $invoice->number,
            $invoice->total,
            optional($invoice->issue_date)->toDateString(),
            $invoice->customer_id,
        ]);

        return 'SIM-'.hash('sha384', $payload);
    }
}
