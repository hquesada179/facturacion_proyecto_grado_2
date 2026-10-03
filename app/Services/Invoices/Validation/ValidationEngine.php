<?php

namespace App\Services\Invoices\Validation;

use App\Services\Invoices\Validation\Rules\RuleInterface;

/**
 * Orchestrates every rule against a ValidationContext. Rules are small,
 * single-purpose and grouped by domain under Rules/ — nothing HTTP-aware
 * lives here, so this can be unit tested with a hand-built context.
 */
class ValidationEngine
{
    /** @param RuleInterface[] $rules */
    public function __construct(private readonly array $rules = []) {}

    public static function default(): self
    {
        return new self(self::defaultRules());
    }

    /** @return RuleInterface[] */
    public static function defaultRules(): array
    {
        return [
            new Rules\Customer\CustomerExistsRule,
            new Rules\Customer\CustomerIsActiveRule,
            new Rules\Customer\CustomerIdentificationTypeValidRule,
            new Rules\Customer\CustomerIdentificationNumberPresentRule,
            new Rules\Customer\CustomerFinalConsumerConfiguredRule,
            new Rules\Customer\CustomerDvValidRule,
            new Rules\Customer\CustomerEmailFormatRule,

            new Rules\Items\AtLeastOneLineRule,
            new Rules\Items\ProductExistsRule,
            new Rules\Items\ProductIsActiveRule,
            new Rules\Items\ProductNotDiscontinuedRule,
            new Rules\Items\DescriptionPresentRule,
            new Rules\Items\UnitOfMeasurePresentRule,

            new Rules\Quantities\QuantityGreaterThanZeroRule,
            new Rules\Quantities\QuantityDecimalPrecisionRule,

            new Rules\Prices\PriceNonNegativeRule,
            new Rules\Prices\ZeroPriceWarningRule,
            new Rules\Prices\CatalogDeviationWarningRule,

            new Rules\Discounts\DiscountPercentRangeRule,
            new Rules\Discounts\DiscountNotExceedingGrossRule,

            new Rules\Taxes\TaxIsActiveRule,
            new Rules\Taxes\TaxIsCurrentlyValidRule,
            new Rules\Taxes\TaxNotDuplicatedRule,
            new Rules\Taxes\TaxCalculationConsistentRule,

            new Rules\Totals\LineSumConsistentRule,
            new Rules\Totals\TaxSumConsistentRule,

            new Rules\Payment\CreditRequiresDueDateRule,
            new Rules\Payment\DueDateNotBeforeIssueDateRule,
        ];
    }

    public function run(ValidationContext $context): ValidationResultCollection
    {
        $results = [];

        foreach ($this->rules as $rule) {
            foreach ($rule->check($context) as $result) {
                $results[] = $result;
            }
        }

        return new ValidationResultCollection($results);
    }
}
