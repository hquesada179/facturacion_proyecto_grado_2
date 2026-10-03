<?php

namespace App\Services\Invoices\Validation\Rules\Discounts;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Re-derives the raw (unclamped) discount independently of
 * InvoiceCalculator, so this still fires even if a future change feeds
 * the calculator a pre-clamped value.
 */
final class DiscountNotExceedingGrossRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $results = [];

        foreach ($context->lines as $line) {
            $gross = BigDecimal::of($line->quantity)->multipliedBy($line->unitPrice);
            $rawDiscount = $gross
                ->multipliedBy($line->discountPercent)
                ->dividedBy(100, 10, RoundingMode::HalfEven);

            if ($rawDiscount->isLessThanOrEqualTo($gross)) {
                continue;
            }

            $results[] = new ValidationResult(
                codigo: 'PRO-DISCOUNT-002',
                regla: 'Descuento no mayor que el valor bruto',
                severidad: ValidationSeverity::Bloqueo,
                campo: "items.{$line->index}.discount_percent",
                mensaje: 'El descuento de la línea supera su valor bruto.',
                sugerencia: 'Reduce el porcentaje de descuento.',
            );
        }

        return $results;
    }
}
