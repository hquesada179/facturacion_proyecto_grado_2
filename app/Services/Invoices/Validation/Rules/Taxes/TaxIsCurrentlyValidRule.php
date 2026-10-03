<?php

namespace App\Services\Invoices\Validation\Rules\Taxes;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;
use Carbon\Carbon;

final class TaxIsCurrentlyValidRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $results = [];
        $referenceDate = $context->issueDate ?? Carbon::today();

        foreach ($context->lines as $line) {
            foreach ($line->taxes as $tax) {
                $tooEarly = $tax->valid_from !== null && $referenceDate->lt($tax->valid_from);
                $tooLate = $tax->valid_until !== null && $referenceDate->gt($tax->valid_until);

                if (! $tooEarly && ! $tooLate) {
                    continue;
                }

                $results[] = new ValidationResult(
                    codigo: 'PRO-TAX-002',
                    regla: 'Tarifa vigente',
                    severidad: ValidationSeverity::Bloqueo,
                    campo: "items.{$line->index}.taxes",
                    mensaje: "El impuesto {$tax->code} no está vigente en la fecha de la factura.",
                    sugerencia: 'Selecciona un impuesto vigente o ajusta la fecha de emisión.',
                );
            }
        }

        return $results;
    }
}
