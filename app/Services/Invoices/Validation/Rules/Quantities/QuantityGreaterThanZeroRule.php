<?php

namespace App\Services\Invoices\Validation\Rules\Quantities;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;
use Brick\Math\BigDecimal;

final class QuantityGreaterThanZeroRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $results = [];

        foreach ($context->lines as $line) {
            if (BigDecimal::of($line->quantity)->isGreaterThan(0)) {
                continue;
            }

            $results[] = new ValidationResult(
                codigo: 'PRO-QTY-001',
                regla: 'Cantidad mayor que cero',
                severidad: ValidationSeverity::Bloqueo,
                campo: "items.{$line->index}.quantity",
                mensaje: 'La cantidad debe ser mayor que cero.',
                sugerencia: 'Ingresa una cantidad válida.',
            );
        }

        return $results;
    }
}
