<?php

namespace App\Services\Invoices\Validation\Rules\Prices;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;
use Brick\Math\BigDecimal;

/**
 * There is no "sample/gift" flag on products yet, so a price of zero is
 * never blocked outright — just flagged for the user to confirm.
 */
final class ZeroPriceWarningRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $results = [];

        foreach ($context->lines as $line) {
            if (! BigDecimal::of($line->unitPrice)->isZero()) {
                continue;
            }

            $results[] = new ValidationResult(
                codigo: 'PRO-PRICE-002',
                regla: 'Precio en cero',
                severidad: ValidationSeverity::Advertencia,
                campo: "items.{$line->index}.unit_price",
                mensaje: 'El precio unitario es cero.',
                sugerencia: 'Confirma que esta línea corresponde a una muestra o cortesía; de lo contrario, ingresa el precio real.',
            );
        }

        return $results;
    }
}
