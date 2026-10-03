<?php

namespace App\Services\Invoices\Validation\Rules\Prices;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;
use Brick\Math\BigDecimal;

final class PriceNonNegativeRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $results = [];

        foreach ($context->lines as $line) {
            if (! BigDecimal::of($line->unitPrice)->isNegative()) {
                continue;
            }

            $results[] = new ValidationResult(
                codigo: 'PRO-PRICE-001',
                regla: 'Precio no negativo',
                severidad: ValidationSeverity::Bloqueo,
                campo: "items.{$line->index}.unit_price",
                mensaje: 'El precio unitario no puede ser negativo.',
                sugerencia: 'Ingresa un precio mayor o igual a cero.',
            );
        }

        return $results;
    }
}
