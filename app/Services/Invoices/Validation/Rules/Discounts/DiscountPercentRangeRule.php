<?php

namespace App\Services\Invoices\Validation\Rules\Discounts;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;
use Brick\Math\BigDecimal;

final class DiscountPercentRangeRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $results = [];

        foreach ($context->lines as $line) {
            $percent = BigDecimal::of($line->discountPercent);

            if ($percent->isGreaterThanOrEqualTo(0) && $percent->isLessThanOrEqualTo(100)) {
                continue;
            }

            $results[] = new ValidationResult(
                codigo: 'PRO-DISCOUNT-001',
                regla: 'Porcentaje de descuento entre 0 y 100',
                severidad: ValidationSeverity::Bloqueo,
                campo: "items.{$line->index}.discount_percent",
                mensaje: 'El porcentaje de descuento debe estar entre 0 y 100.',
                sugerencia: 'Ingresa un porcentaje de descuento válido.',
            );
        }

        return $results;
    }
}
