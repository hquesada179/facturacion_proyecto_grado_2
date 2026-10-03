<?php

namespace App\Services\Invoices\Validation\Rules\Totals;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;
use Brick\Math\BigDecimal;

final class TaxSumConsistentRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $sum = BigDecimal::zero();

        foreach ($context->lines as $line) {
            $sum = $sum->plus($line->calculated->taxTotal);
        }

        if ($sum->isEqualTo($context->calculation->totalTaxes)) {
            return [];
        }

        return [new ValidationResult(
            codigo: 'PRO-TOTALS-002',
            regla: 'Suma de impuestos coherente',
            severidad: ValidationSeverity::Bloqueo,
            campo: 'total_taxes',
            mensaje: 'La suma de los impuestos de las líneas no coincide con el total de impuestos de la factura.',
            sugerencia: 'Vuelve a calcular la factura.',
        )];
    }
}
