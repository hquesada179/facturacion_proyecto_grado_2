<?php

namespace App\Services\Invoices\Calculation;

use App\Models\Tax;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Reverses a tax-inclusive unit price into its pre-tax base, so that
 * InvoiceCalculator can keep assuming every unit_price it ever receives
 * is already tax-exclusive — exactly like it always has (Fase 3 never
 * touches InvoiceCalculator itself). Applied once, at the moment a
 * fresh price enters the system for a product flagged "precio incluye
 * impuesto"; must never be re-applied to a value that is already a
 * stored base (e.g. when only a line's quantity changes and unit_price
 * is just being passed through unchanged).
 */
final class TaxInclusivePriceResolver
{
    private const SCALE = 2;

    /**
     * @param  iterable<Tax>  $taxes
     */
    public function extractBase(string $grossUnitPrice, iterable $taxes): string
    {
        $rateSum = BigDecimal::zero();

        foreach ($taxes as $tax) {
            $rateSum = $rateSum->plus(BigDecimal::of((string) $tax->rate));
        }

        if ($rateSum->isZero()) {
            return (string) BigDecimal::of($grossUnitPrice)->toScale(self::SCALE, RoundingMode::HalfEven);
        }

        $divisor = BigDecimal::of(100)->plus($rateSum)->dividedBy(100, 10, RoundingMode::HalfEven);

        return (string) BigDecimal::of($grossUnitPrice)->dividedBy($divisor, self::SCALE, RoundingMode::HalfEven);
    }
}
