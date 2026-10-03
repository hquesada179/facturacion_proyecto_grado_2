<?php

namespace App\Services\Invoices\Validation\Rules\Items;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;

final class DescriptionPresentRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $results = [];

        foreach ($context->lines as $line) {
            if (trim($line->description) !== '') {
                continue;
            }

            $results[] = new ValidationResult(
                codigo: 'PRO-ITEMS-005',
                regla: 'Descripción presente',
                severidad: ValidationSeverity::Bloqueo,
                campo: "items.{$line->index}.description",
                mensaje: 'La línea no tiene descripción.',
                sugerencia: 'Escribe una descripción para el producto o servicio.',
            );
        }

        return $results;
    }
}
