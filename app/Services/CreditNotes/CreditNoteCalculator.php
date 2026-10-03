<?php

namespace App\Services\CreditNotes;

use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

class CreditNoteCalculator
{
    private const SCALE = 2;

    private const INTERMEDIATE_SCALE = 10;

    public function recalculate(CreditNote $creditNote): CreditNote
    {
        $items = $creditNote->items()->orderBy('id')->get();

        $subtotal = BigDecimal::zero();
        $taxTotal = BigDecimal::zero();
        $total = BigDecimal::zero();

        DB::transaction(function () use ($creditNote, $items, &$subtotal, &$taxTotal, &$total): void {
            foreach ($items as $item) {
                $line = $this->calculateLine($item);

                $item->forceFill($line)->save();

                $subtotal = $subtotal->plus($line['taxable_base']);
                $taxTotal = $taxTotal->plus($line['tax_total']);
                $total = $total->plus($line['line_total']);
            }

            $creditNote->forceFill([
                'subtotal' => (string) $subtotal->toScale(self::SCALE, RoundingMode::HalfEven),
                'tax_total' => (string) $taxTotal->toScale(self::SCALE, RoundingMode::HalfEven),
                'total' => (string) $total->toScale(self::SCALE, RoundingMode::HalfEven),
            ])->save();
        });

        return $creditNote->refresh();
    }

    /**
     * @return array{discount_total: string, taxable_base: string, tax_total: string, line_total: string, tax_snapshot: array<int, array<string, mixed>>}
     */
    private function calculateLine(CreditNoteItem $item): array
    {
        $quantity = BigDecimal::of((string) $item->credited_quantity);
        $unitPrice = BigDecimal::of((string) $item->unit_price);
        $gross = $quantity->multipliedBy($unitPrice);

        $discountPercent = BigDecimal::of((string) $item->discount_percent);
        $discount = $gross
            ->multipliedBy($discountPercent)
            ->dividedBy(100, self::INTERMEDIATE_SCALE, RoundingMode::HalfEven);

        if ($gross->isPositive() && $discount->isGreaterThan($gross)) {
            $discount = $gross;
        }

        $taxableBase = $gross->minus($discount)->toScale(self::SCALE, RoundingMode::HalfEven);
        $lineTaxTotal = BigDecimal::zero();
        $taxSnapshot = [];

        foreach ($item->tax_snapshot ?? [] as $tax) {
            $rate = BigDecimal::of((string) ($tax['rate'] ?? 0));
            $value = $taxableBase
                ->multipliedBy($rate)
                ->dividedBy(100, self::INTERMEDIATE_SCALE, RoundingMode::HalfEven)
                ->toScale(self::SCALE, RoundingMode::HalfEven);

            $lineTaxTotal = $lineTaxTotal->plus($value);

            $taxSnapshot[] = [
                'tax_id' => $tax['tax_id'] ?? null,
                'code' => $tax['code'] ?? 'TAX',
                'name' => $tax['name'] ?? 'Impuesto',
                'rate' => (string) $rate,
                'base' => (string) $taxableBase,
                'value' => (string) $value,
            ];
        }

        $lineTaxTotal = $lineTaxTotal->toScale(self::SCALE, RoundingMode::HalfEven);
        $lineTotal = $taxableBase->plus($lineTaxTotal)->toScale(self::SCALE, RoundingMode::HalfEven);

        return [
            'discount_total' => (string) $discount->toScale(self::SCALE, RoundingMode::HalfEven),
            'taxable_base' => (string) $taxableBase,
            'tax_total' => (string) $lineTaxTotal,
            'line_total' => (string) $lineTotal,
            'tax_snapshot' => $taxSnapshot,
        ];
    }
}
