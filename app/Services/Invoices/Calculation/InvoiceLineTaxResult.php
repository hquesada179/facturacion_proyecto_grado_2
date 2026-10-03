<?php

namespace App\Services\Invoices\Calculation;

final readonly class InvoiceLineTaxResult
{
    public function __construct(
        public ?int $taxId,
        public string $code,
        public string $name,
        public string $base,
        public string $rate,
        public string $value,
    ) {}

    public function toArray(): array
    {
        return [
            'tax_id' => $this->taxId,
            'code' => $this->code,
            'name' => $this->name,
            'base' => $this->base,
            'rate' => $this->rate,
            'value' => $this->value,
        ];
    }
}
