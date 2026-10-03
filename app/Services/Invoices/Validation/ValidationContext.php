<?php

namespace App\Services\Invoices\Validation;

use App\Models\Company;
use App\Models\Customer;
use App\Services\Invoices\Calculation\InvoiceCalculationResult;
use Carbon\CarbonInterface;

/**
 * Immutable snapshot of a draft invoice, already resolved and calculated,
 * handed to every rule. Rules never touch Eloquent or HTTP themselves.
 */
final readonly class ValidationContext
{
    /** @param InvoiceLineContext[] $lines */
    public function __construct(
        public Company $company,
        public ?Customer $customer,
        public array $lines,
        public InvoiceCalculationResult $calculation,
        public ?string $paymentType = null,
        public ?CarbonInterface $issueDate = null,
        public ?CarbonInterface $dueDate = null,
    ) {}
}
