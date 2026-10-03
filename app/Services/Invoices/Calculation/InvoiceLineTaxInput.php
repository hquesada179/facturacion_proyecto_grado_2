<?php

namespace App\Services\Invoices\Calculation;

/**
 * One tax applicable to a draft line, as resolved from the tax catalog.
 * `rate` is a decimal string (e.g. "19.00"), never a float.
 */
final readonly class InvoiceLineTaxInput
{
    public function __construct(
        public ?int $taxId,
        public string $code,
        public string $name,
        public string $rate,
    ) {}
}
