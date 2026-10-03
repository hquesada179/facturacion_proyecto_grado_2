<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemTax;
use App\Services\Invoices\Calculation\InvoiceCalculationResult;
use App\Services\Invoices\Calculation\InvoiceCalculator;
use App\Services\Invoices\Calculation\InvoiceDraftInput;
use App\Services\Invoices\Calculation\InvoiceLineInput;
use App\Services\Invoices\Calculation\InvoiceLineTaxInput;
use Illuminate\Support\Facades\DB;

/**
 * The only place invoice totals are written. Always reloads every line
 * from the DB, recalculates the whole draft in one InvoiceCalculator
 * pass (Fase 3), and writes the result back — so the table the user sees
 * can never drift from what was actually entered (the bug this phase
 * fixes: editing one line's quantity used to leave stale totals behind).
 */
class InvoiceRecalculationService
{
    public function __construct(private readonly InvoiceCalculator $calculator) {}

    public function recalculate(Invoice $invoice): InvoiceCalculationResult
    {
        $items = $invoice->items()->with('itemTaxes')->orderBy('id')->get();

        $lineInputs = $items->map(static fn (InvoiceItem $item): InvoiceLineInput => new InvoiceLineInput(
            productServiceId: $item->product_service_id,
            description: $item->description,
            unit: $item->unit,
            quantity: (string) $item->quantity,
            unitPrice: (string) $item->unit_price,
            discountPercent: (string) $item->discount_percent,
            charges: '0',
            taxes: $item->itemTaxes->map(static fn (InvoiceItemTax $tax): InvoiceLineTaxInput => new InvoiceLineTaxInput(
                taxId: $tax->tax_id,
                code: $tax->code,
                name: $tax->name,
                rate: (string) $tax->rate,
            ))->all(),
        ))->all();

        $result = $this->calculator->calculate(new InvoiceDraftInput(
            lines: $lineInputs,
            currency: $invoice->currency ?? 'COP',
        ));

        DB::transaction(function () use ($items, $invoice, $result): void {
            foreach ($items as $index => $item) {
                $lineResult = $result->lines[$index];

                $item->forceFill([
                    'discount_total' => $lineResult->discountAmount,
                    'taxable_base' => $lineResult->taxableBase,
                    'tax_total' => $lineResult->taxTotal,
                    'line_total' => $lineResult->lineTotal,
                ])->save();

                $item->itemTaxes()->delete();

                foreach ($lineResult->taxes as $taxResult) {
                    $item->itemTaxes()->create([
                        'tax_id' => $taxResult->taxId,
                        'code' => $taxResult->code,
                        'name' => $taxResult->name,
                        'base' => $taxResult->base,
                        'rate' => $taxResult->rate,
                        'value' => $taxResult->value,
                    ]);
                }
            }

            $invoice->forceFill([
                'subtotal' => $result->subtotal,
                'tax_total' => $result->totalTaxes,
                'total' => $result->total,
            ])->save();
        });

        return $result;
    }
}
