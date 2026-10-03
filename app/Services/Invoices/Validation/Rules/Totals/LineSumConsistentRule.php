<?php

namespace App\Services\Invoices\Validation\Rules\Totals;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;
use Brick\Math\BigDecimal;

final class LineSumConsistentRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $sum = BigDecimal::zero();

        foreach ($context->lines as $line) {
            $sum = $sum->plus($line->calculated->taxableBase);
        }

        if ($sum->isEqualTo($context->calculation->subtotal)) {
            return [];
        }

        return [new ValidationResult(
            codigo: 'PRO-TOTALS-001',
            regla: 'Suma de líneas coherente',
            severidad: ValidationSeverity::Bloqueo,
            campo: 'subtotal',
            mensaje: 'La suma de las bases gravables de las líneas no coincide con el subtotal de la factura.',
            sugerencia: 'Vuelve a calcular la factura.',
        )];
    }
}
