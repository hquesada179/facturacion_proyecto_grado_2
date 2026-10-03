<?php

namespace App\Services\Invoices\Validation;

use App\Models\ProductService;
use App\Models\Tax;
use App\Services\Invoices\Calculation\InvoiceLineResult;

/**
 * Everything a rule needs to judge one draft line, already resolved from
 * the catalog (no further Eloquent lookups should happen inside a rule).
 */
final readonly class InvoiceLineContext
{
    /** @param Tax[] $taxes */
    public function __construct(
        public int $index,
        public ?int $productServiceId,
        public bool $productReferenced,
        public ?ProductService $product,
        public string $description,
        public string $unit,
        public string $quantity,
        public string $unitPrice,
        public string $discountPercent,
        public array $taxes,
        public InvoiceLineResult $calculated,
    ) {}
}
