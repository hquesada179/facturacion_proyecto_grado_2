<?php

namespace App\Services\Invoices\Validation\Rules\Taxes;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Independently recomputes base × tarifa / 100 for every tax already
 * calculated by InvoiceCalculator and compares it against the stored
 * value — a defensive double-check, not merely trusting the calculator.
 */
final class TaxCalculationConsistentRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $results = [];

        foreach ($context->lines as $line) {
            foreach ($line->calculated->taxes as $taxResult) {
                $expected = BigDecimal::of($taxResult->base)
                    ->multipliedBy($taxResult->rate)
                    ->dividedBy(100, 10, RoundingMode::HalfEven)
                    ->toScale(2, RoundingMode::HalfEven);

                if ($expected->isEqualTo($taxResult->value)) {
                    continue;
                }

                $results[] = new ValidationResult(
                    codigo: 'PRO-TAX-004',
                    regla: 'Cálculo de impuesto consistente',
                    severidad: ValidationSeverity::Bloqueo,
                    campo: "items.{$line->index}.taxes",
                    mensaje: "El valor calculado del impuesto {$taxResult->code} no coincide con base × tarifa / 100.",
                    sugerencia: 'Vuelve a calcular la factura.',
                );
            }
        }

        return $results;
    }
}
