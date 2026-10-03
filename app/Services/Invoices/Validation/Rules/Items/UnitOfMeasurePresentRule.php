<?php

namespace App\Services\Invoices\Validation\Rules\Items;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;

final class UnitOfMeasurePresentRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $results = [];

        foreach ($context->lines as $line) {
            if (trim($line->unit) !== '') {
                continue;
            }

            $results[] = new ValidationResult(
                codigo: 'PRO-ITEMS-006',
                regla: 'Unidad de medida válida',
                severidad: ValidationSeverity::Bloqueo,
                campo: "items.{$line->index}.unit",
                mensaje: 'La línea no tiene unidad de medida.',
                sugerencia: 'Indica la unidad de medida del producto o servicio.',
            );
        }

        return $results;
    }
}
