<?php

namespace App\Services\Invoices\Validation\Rules;

use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;

interface RuleInterface
{
    /** @return ValidationResult[] */
    public function check(ValidationContext $context): array;
}
