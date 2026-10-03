<?php

namespace App\Services\Invoices\Calculation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Pure domain service: no Eloquent, no HTTP, no controllers. Given an
 * InvoiceDraftInput it always returns the same InvoiceCalculationResult —
 * safe to unit test directly.
 *
 * Monetary precision: brick/math's BigDecimal, not float/BCMath. Floats
 * cannot represent most decimal fractions exactly (0.1 + 0.2 !== 0.3 in
 * IEEE-754), which is unacceptable for money. Plain BCMath would work too,
 * but its functions operate on strings with no first-class rounding modes,
 * so implementing half-to-even ("banker's rounding", required below) means
 * hand-parsing the string for the exact tie-breaking digit. BigDecimal
 * exposes RoundingMode::HalfEven directly on dividedBy()/toScale(), so the
 * rounding policy is declared once instead of hand-rolled.
 *
 * Rounding policy: amounts are kept at full precision through the
 * intermediate steps of a line (gross, discount, taxable base) and rounded
 * to 2 decimals (HALF_EVEN) only once each line's taxable base, taxes and
 * line total are final. Invoice-level totals are the *sum of the
 * already-rounded per-line figures*, never a separate rounding of an exact
 * sum — this guarantees "sum of lines equals the subtotal" and "sum of
 * taxes equals the tax total" hold by construction, which the validation
 * engine's consistency rules rely on.
 */
class InvoiceCalculator
{
    private const SCALE = 2;

    private const INTERMEDIATE_SCALE = 10;

    public function calculate(InvoiceDraftInput $draft): InvoiceCalculationResult
    {
        $lines = [];
        $subtotal = BigDecimal::zero();
        $totalDiscounts = BigDecimal::zero();
        $totalCharges = BigDecimal::zero();
        $totalTaxes = BigDecimal::zero();
        /** @var array<string, array{code: string, name: string, rate: string, base: BigDecimal, value: BigDecimal}> $taxesByCode */
        $taxesByCode = [];

        foreach ($draft->lines as $index => $line) {
            $lineResult = $this->calculateLine($index, $line, $taxesByCode);

            $lines[] = $lineResult;
            $subtotal = $subtotal->plus($lineResult->taxableBase);
            $totalDiscounts = $totalDiscounts->plus($lineResult->discountAmount);
            $totalCharges = $totalCharges->plus($lineResult->chargesAmount);
            $totalTaxes = $totalTaxes->plus($lineResult->taxTotal);
        }

        // BigDecimal::zero() defaults to scale 0 ("0", not "0.00"); without
        // this, an invoice with no lines (or a line with no taxes) would
        // format its totals inconsistently with the per-line figures.
        $subtotal = $subtotal->toScale(self::SCALE, RoundingMode::HalfEven);
        $totalDiscounts = $totalDiscounts->toScale(self::SCALE, RoundingMode::HalfEven);
        $totalCharges = $totalCharges->toScale(self::SCALE, RoundingMode::HalfEven);
        $totalTaxes = $totalTaxes->toScale(self::SCALE, RoundingMode::HalfEven);

        $globalCharges = BigDecimal::of($draft->globalCharges)->toScale(self::SCALE, RoundingMode::HalfEven);
        $total = $subtotal->plus($totalTaxes)->plus($globalCharges);

        $taxesByCodeResult = [];
        foreach ($taxesByCode as $code => $data) {
            $taxesByCodeResult[$code] = [
                'code' => $data['code'],
                'name' => $data['name'],
                'rate' => $data['rate'],
                'base' => (string) $data['base'],
                'value' => (string) $data['value'],
            ];
        }

        return new InvoiceCalculationResult(
            lines: $lines,
            subtotal: (string) $subtotal,
            totalDiscounts: (string) $totalDiscounts,
            totalCharges: (string) $totalCharges,
            globalCharges: (string) $globalCharges,
            taxesByCode: $taxesByCodeResult,
            totalTaxes: (string) $totalTaxes,
            total: (string) $total,
        );
    }

    /**
     * @param  array<string, array{code: string, name: string, rate: string, base: BigDecimal, value: BigDecimal}>  $taxesByCode
     */
    private function calculateLine(int $index, InvoiceLineInput $line, array &$taxesByCode): InvoiceLineResult
    {
        $quantity = BigDecimal::of($line->quantity);
        $unitPrice = BigDecimal::of($line->unitPrice);

        // valor bruto = cantidad × precio unitario
        $gross = $quantity->multipliedBy($unitPrice);

        $discountPercent = BigDecimal::of($line->discountPercent);
        $discountAmount = $gross
            ->multipliedBy($discountPercent)
            ->dividedBy(100, self::INTERMEDIATE_SCALE, RoundingMode::HalfEven);

        // A discount can never exceed the line's gross amount (only meaningful
        // for a positive gross — a negative price has no "gross to exceed").
        if ($gross->isPositive() && $discountAmount->isGreaterThan($gross)) {
            $discountAmount = $gross;
        }

        $charges = BigDecimal::of($line->charges);

        // valor de línea (base gravable) = valor bruto - descuentos + cargos
        $taxableBase = $gross->minus($discountAmount)->plus($charges)->toScale(self::SCALE, RoundingMode::HalfEven);

        $taxResults = [];
        $lineTaxTotal = BigDecimal::zero();

        foreach ($line->taxes as $taxInput) {
            $rate = BigDecimal::of($taxInput->rate);
            $value = $taxableBase
                ->multipliedBy($rate)
                ->dividedBy(100, self::INTERMEDIATE_SCALE, RoundingMode::HalfEven)
                ->toScale(self::SCALE, RoundingMode::HalfEven);

            $lineTaxTotal = $lineTaxTotal->plus($value);

            $taxResults[] = new InvoiceLineTaxResult(
                taxId: $taxInput->taxId,
                code: $taxInput->code,
                name: $taxInput->name,
                base: (string) $taxableBase,
                rate: (string) $rate,
                value: (string) $value,
            );

            $key = $taxInput->code;
            if (! isset($taxesByCode[$key])) {
                $taxesByCode[$key] = [
                    'code' => $taxInput->code,
                    'name' => $taxInput->name,
                    'rate' => (string) $rate,
                    'base' => BigDecimal::zero(),
                    'value' => BigDecimal::zero(),
                ];
            }
            $taxesByCode[$key]['base'] = $taxesByCode[$key]['base']->plus($taxableBase);
            $taxesByCode[$key]['value'] = $taxesByCode[$key]['value']->plus($value);
        }

        $lineTaxTotal = $lineTaxTotal->toScale(self::SCALE, RoundingMode::HalfEven);
        $lineTotal = $taxableBase->plus($lineTaxTotal);

        return new InvoiceLineResult(
            index: $index,
            description: $line->description,
            grossAmount: (string) $gross->toScale(self::SCALE, RoundingMode::HalfEven),
            discountAmount: (string) $discountAmount->toScale(self::SCALE, RoundingMode::HalfEven),
            chargesAmount: (string) $charges->toScale(self::SCALE, RoundingMode::HalfEven),
            taxableBase: (string) $taxableBase,
            taxes: $taxResults,
            taxTotal: (string) $lineTaxTotal,
            lineTotal: (string) $lineTotal,
        );
    }
}
