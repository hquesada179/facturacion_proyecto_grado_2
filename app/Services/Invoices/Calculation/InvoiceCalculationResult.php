<?php

namespace App\Services\Invoices\Calculation;

final readonly class InvoiceCalculationResult
{
    /**
     * @param  InvoiceLineResult[]  $lines
     * @param  array<string, array{code: string, name: string, rate: string, base: string, value: string}>  $taxesByCode
     */
    public function __construct(
        public array $lines,
        public string $subtotal,
        public string $totalDiscounts,
        public string $totalCharges,
        public string $globalCharges,
        public array $taxesByCode,
        public string $totalTaxes,
        public string $total,
    ) {}

    public function toArray(): array
    {
        return [
            'lines' => array_map(static fn (InvoiceLineResult $line): array => $line->toArray(), $this->lines),
            'subtotal' => $this->subtotal,
            'total_discounts' => $this->totalDiscounts,
            'total_charges' => $this->totalCharges,
            'global_charges' => $this->globalCharges,
            'taxes_by_code' => array_values($this->taxesByCode),
            'total_taxes' => $this->totalTaxes,
            'total' => $this->total,
        ];
    }
}
