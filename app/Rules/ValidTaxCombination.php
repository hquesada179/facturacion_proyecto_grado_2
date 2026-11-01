<?php

namespace App\Rules;

use App\Models\Tax;
use App\Support\TaxCombinationPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates the array of tax IDs submitted for a product or an invoice
 * line against App\Support\TaxCombinationPolicy, so the same tax-rate
 * array that already exists today (StoreProductServiceRequest's
 * `taxes`, StoreInvoiceItemRequest's `tax_ids`, ...) stays the single
 * source of truth — no new field, no duplicated rule.
 */
class ValidTaxCombination implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || $value === []) {
            return;
        }

        $taxes = Tax::whereIn('id', $value)->get(['id', 'code', 'nature', 'condition']);

        $violation = TaxCombinationPolicy::violationFor($taxes);

        if ($violation !== null) {
            $fail($violation);
        }
    }
}
