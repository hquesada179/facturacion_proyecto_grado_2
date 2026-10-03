<?php

namespace App\Services\Invoices\Validation\Rules\Taxes;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;

final class TaxIsActiveRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $results = [];

        foreach ($context->lines as $line) {
            foreach ($line->taxes as $tax) {
                if ($tax->is_active) {
                    continue;
                }

                $results[] = new ValidationResult(
                    codigo: 'PRO-TAX-001',
                    regla: 'Impuesto activo',
                    severidad: ValidationSeverity::Bloqueo,
                    campo: "items.{$line->index}.taxes",
                    mensaje: "El impuesto {$tax->code} está inactivo.",
                    sugerencia: 'Quita este impuesto de la línea o actívalo en el catálogo.',
                );
            }
        }

        return $results;
    }
}
