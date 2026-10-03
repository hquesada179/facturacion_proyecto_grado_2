<?php

namespace App\Services\Invoices\Calculation;

/**
 * One draft invoice line. All numeric fields are decimal strings — never
 * float — so InvoiceCalculator can hand them straight to brick/math
 * without ever passing through IEEE-754 binary floating point.
 */
final readonly class InvoiceLineInput
{
    /** @param InvoiceLineTaxInput[] $taxes */
    public function __construct(
        public ?int $productServiceId,
        public string $description,
        public string $unit,
        public string $quantity,
        public string $unitPrice,
        public string $discountPercent = '0',
        public string $charges = '0',
        public array $taxes = [],
    ) {}
}
