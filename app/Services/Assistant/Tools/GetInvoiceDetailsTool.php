<?php

namespace App\Services\Assistant\Tools;

use App\Models\Invoice;
use App\Models\InvoiceItemTax;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class GetInvoiceDetailsTool
{
    public function handle(User $user, ?int $invoiceId = null, ?string $number = null): array
    {
        $invoice = $this->findInvoice($user, $invoiceId, $number);

        if (! $invoice || Gate::forUser($user)->denies('view', $invoice)) {
            return ['tool' => 'invoice_details', 'found' => false];
        }

        $invoice->loadMissing(['customer', 'items.itemTaxes', 'creditNotes']);

        return [
            'tool' => 'invoice_details',
            'found' => true,
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'customer' => $invoice->customer?->name,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'subtotal' => (float) $invoice->subtotal,
                'tax_total' => (float) $invoice->tax_total,
                'total' => (float) $invoice->total,
                'issue_date' => $invoice->issue_date?->toDateString(),
                'issued_at' => $invoice->issued_at?->toDateTimeString(),
                'validation_result' => $invoice->dian_simulation_result,
                'validation_message' => $invoice->dian_simulation_message,
                'credit_notes_count' => $invoice->creditNotes->count(),
                'tax_breakdown' => $this->taxBreakdown($invoice),
                'lines' => $invoice->items->map(fn ($item): array => [
                    'product_code' => $item->product_code,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'tax_total' => (float) $item->tax_total,
                    'line_total' => (float) $item->line_total,
                ])->values()->all(),
            ],
        ];
    }

    private function findInvoice(User $user, ?int $invoiceId, ?string $number): ?Invoice
    {
        $query = Invoice::withoutGlobalScopes()
            ->where('company_id', $user->company_id);

        if ($invoiceId !== null) {
            return $query->whereKey($invoiceId)->first();
        }

        if ($number !== null && trim($number) !== '') {
            return $query->where('number', trim($number))->first();
        }

        return null;
    }

    /**
     * @return array<int, array{code: string, name: string, base: string, rate: float, tax: string}>
     */
    private function taxBreakdown(Invoice $invoice): array
    {
        $taxRows = $invoice->items
            ->flatMap(fn ($item): Collection => $item->itemTaxes)
            ->groupBy(fn (InvoiceItemTax $tax): string => $tax->code.'|'.$tax->name.'|'.(string) $tax->rate)
            ->map(function (Collection $taxes): array {
                /** @var InvoiceItemTax $first */
                $first = $taxes->first();

                $base = $taxes->reduce(fn (BigDecimal $carry, InvoiceItemTax $tax): BigDecimal => $carry->plus($tax->base), BigDecimal::zero());
                $value = $taxes->reduce(fn (BigDecimal $carry, InvoiceItemTax $tax): BigDecimal => $carry->plus($tax->value), BigDecimal::zero());

                return [
                    'code' => $first->code,
                    'name' => $first->name,
                    'base' => (string) $base->toScale(2, RoundingMode::HalfEven),
                    'rate' => (float) $first->rate,
                    'tax' => (string) $value->toScale(2, RoundingMode::HalfEven),
                ];
            })
            ->values();

        if ($taxRows->isNotEmpty()) {
            return $taxRows->all();
        }

        return $invoice->items
            ->filter(fn ($item): bool => (float) $item->tax_total > 0)
            ->map(fn ($item): array => [
                'code' => 'IMP',
                'name' => 'Impuesto de línea',
                'base' => (float) ($item->taxable_base ?: $item->line_total),
                'rate' => (float) $item->tax_rate,
                'tax' => (float) $item->tax_total,
            ])
            ->values()
            ->all();
    }
}
