<?php

namespace App\Services\CreditNotes;

use App\Models\CreditNote;

class CreditNoteCufeGenerator
{
    public function generate(CreditNote $creditNote): string
    {
        $creditNote->loadMissing('invoice.company');

        $payload = implode('|', [
            $creditNote->invoice->company->nit,
            $creditNote->number,
            $creditNote->invoice->number,
            $creditNote->total,
            optional($creditNote->issued_at)->toDateString(),
            $creditNote->invoice_id,
        ]);

        return 'SIM-NC-'.hash('sha384', $payload);
    }
}
