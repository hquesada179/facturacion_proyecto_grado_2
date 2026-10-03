<?php

namespace App\Services\Invoices\Validation\Rules\Quantities;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;
use Brick\Math\BigDecimal;

/**
 * invoice_items.quantity is decimal(12,2): more than 2 meaningful decimal
 * digits will be silently rounded on save. Warn instead of block, since
 * rounding is safe, just potentially surprising.
 */
final class QuantityDecimalPrecisionRule implements RuleInterface
{
    private const MAX_SCALE = 2;

    public function check(ValidationContext $context): array
    {
        $results = [];

        foreach ($context->lines as $line) {
            $scale = BigDecimal::of($line->quantity)->strippedOfTrailingZeros()->getScale();

            if ($scale <= self::MAX_SCALE) {
                continue;
            }

            $results[] = new ValidationResult(
                codigo: 'PRO-QTY-002',
                regla: 'Precisión decimal coherente',
                severidad: ValidationSeverity::Advertencia,
                campo: "items.{$line->index}.quantity",
                mensaje: 'La cantidad tiene más de 2 decimales y será redondeada al guardar.',
                sugerencia: 'Usa máximo 2 decimales para la cantidad.',
            );
        }

        return $results;
    }
}
