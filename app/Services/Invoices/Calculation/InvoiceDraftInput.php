<?php

namespace App\Services\Invoices\Calculation;

final readonly class InvoiceDraftInput
{
    /** @param InvoiceLineInput[] $lines */
    public function __construct(
        public array $lines = [],
        public string $globalCharges = '0',
        public string $currency = 'COP',
    ) {}
}
