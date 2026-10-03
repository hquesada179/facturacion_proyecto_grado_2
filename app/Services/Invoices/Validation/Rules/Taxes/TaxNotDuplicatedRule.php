<?php

namespace App\Services\Invoices\Validation\Rules\Taxes;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;

final class TaxNotDuplicatedRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $results = [];

        foreach ($context->lines as $line) {
            $seen = [];

            foreach ($line->taxes as $tax) {
                $key = $tax->id ?? $tax->code;

                if (! in_array($key, $seen, true)) {
                    $seen[] = $key;

                    continue;
                }

                $results[] = new ValidationResult(
                    codigo: 'PRO-TAX-003',
                    regla: 'Tributo no repetido',
                    severidad: ValidationSeverity::Bloqueo,
                    campo: "items.{$line->index}.taxes",
                    mensaje: "El impuesto {$tax->code} está aplicado más de una vez en la misma línea.",
                    sugerencia: 'Elimina la aplicación duplicada del impuesto.',
                );
            }
        }

        return $results;
    }
}
