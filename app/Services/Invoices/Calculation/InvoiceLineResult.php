<?php

namespace App\Services\Invoices\Calculation;

final readonly class InvoiceLineResult
{
    /** @param InvoiceLineTaxResult[] $taxes */
    public function __construct(
        public int $index,
        public string $description,
        public string $grossAmount,
        public string $discountAmount,
        public string $chargesAmount,
        public string $taxableBase,
        public array $taxes,
        public string $taxTotal,
        public string $lineTotal,
    ) {}

    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'description' => $this->description,
            'gross_amount' => $this->grossAmount,
            'discount_amount' => $this->discountAmount,
            'charges_amount' => $this->chargesAmount,
            'taxable_base' => $this->taxableBase,
            'taxes' => array_map(static fn (InvoiceLineTaxResult $tax): array => $tax->toArray(), $this->taxes),
            'tax_total' => $this->taxTotal,
            'line_total' => $this->lineTotal,
        ];
    }
}
