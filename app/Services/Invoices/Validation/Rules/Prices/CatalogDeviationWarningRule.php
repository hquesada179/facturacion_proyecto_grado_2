<?php

namespace App\Services\Invoices\Validation\Rules\Prices;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * A large gap between the billed price and the product's catalog price
 * is worth a second look, but it is a legitimate commercial decision
 * (discounted deal, price update in progress, etc.) — never a blocker.
 */
final class CatalogDeviationWarningRule implements RuleInterface
{
    private const THRESHOLD_PERCENT = '30';

    public function check(ValidationContext $context): array
    {
        $results = [];

        foreach ($context->lines as $line) {
            if ($line->product === null) {
                continue;
            }

            $catalogPrice = BigDecimal::of((string) $line->product->price);

            if ($catalogPrice->isZero()) {
                continue;
            }

            $unitPrice = BigDecimal::of($line->unitPrice);
            $deviation = $unitPrice->minus($catalogPrice)->abs();
            $threshold = $catalogPrice
                ->multipliedBy(self::THRESHOLD_PERCENT)
                ->dividedBy(100, 10, RoundingMode::HalfEven);

            if ($deviation->isLessThanOrEqualTo($threshold)) {
                continue;
            }

            $results[] = new ValidationResult(
                codigo: 'PRO-PRICE-003',
                regla: 'Desviación frente al catálogo',
                severidad: ValidationSeverity::Advertencia,
                campo: "items.{$line->index}.unit_price",
                mensaje: 'El precio de la línea difiere en más de un 30% del precio de catálogo del producto.',
                sugerencia: 'Verifica que el precio ingresado sea el correcto.',
            );
        }

        return $results;
    }
}
