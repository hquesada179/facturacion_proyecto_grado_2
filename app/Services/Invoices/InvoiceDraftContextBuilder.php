<?php

namespace App\Services\Invoices;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductService;
use App\Models\Tax;
use App\Services\Invoices\Calculation\InvoiceCalculator;
use App\Services\Invoices\Calculation\InvoiceDraftInput;
use App\Services\Invoices\Calculation\InvoiceLineInput;
use App\Services\Invoices\Calculation\InvoiceLineTaxInput;
use App\Services\Invoices\Validation\InvoiceLineContext;
use App\Services\Invoices\Validation\ValidationContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The only piece of the calculation/validation pipeline that talks to
 * Eloquent. Takes the already-shape-validated draft array from
 * ValidateInvoiceDraftRequest, resolves Customer/ProductService/Tax
 * (always company-scoped — see CompanyScope), runs InvoiceCalculator, and
 * hands back a ValidationContext ready for ValidationEngine.
 */
class InvoiceDraftContextBuilder
{
    public function __construct(private readonly InvoiceCalculator $calculator) {}

    public function build(Company $company, array $data): ValidationContext
    {
        $customer = isset($data['customer_id']) && $data['customer_id'] !== null
            ? Customer::find($data['customer_id'])
            : null;

        $rawItems = $data['items'] ?? [];
        $lineInputs = [];
        $lineMeta = [];

        foreach ($rawItems as $item) {
            $productReferenced = array_key_exists('product_service_id', $item) && $item['product_service_id'] !== null;
            $product = $productReferenced
                ? ProductService::with('taxes')->find($item['product_service_id'])
                : null;

            $description = (string) ($item['description'] ?? $product?->name ?? '');
            $unit = (string) ($item['unit'] ?? $product?->unit ?? '');
            $quantity = $this->numericString($item['quantity'] ?? null);
            $unitPrice = $this->numericString($item['unit_price'] ?? $product?->price);
            $discountPercent = $this->numericString($item['discount_percent'] ?? null);

            $taxes = array_key_exists('tax_ids', $item) && $item['tax_ids'] !== null
                ? Tax::whereIn('id', $item['tax_ids'])->get()
                : ($product?->taxes ?? new Collection);

            $taxInputs = $taxes->map(static fn (Tax $tax): InvoiceLineTaxInput => new InvoiceLineTaxInput(
                taxId: $tax->id,
                code: $tax->code ?? ('TAX-'.$tax->id),
                name: $tax->name,
                rate: (string) $tax->rate,
            ))->all();

            $lineInputs[] = new InvoiceLineInput(
                productServiceId: $product?->id,
                description: $description,
                unit: $unit,
                quantity: $quantity,
                unitPrice: $unitPrice,
                discountPercent: $discountPercent,
                charges: '0',
                taxes: $taxInputs,
            );

            $lineMeta[] = [
                'productReferenced' => $productReferenced,
                'product' => $product,
                'taxes' => $taxes->all(),
                'description' => $description,
                'unit' => $unit,
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'discountPercent' => $discountPercent,
            ];
        }

        $calculation = $this->calculator->calculate(new InvoiceDraftInput(lines: $lineInputs));

        $lineContexts = [];
        foreach ($calculation->lines as $index => $calculatedLine) {
            $meta = $lineMeta[$index];

            $lineContexts[] = new InvoiceLineContext(
                index: $index,
                productServiceId: $meta['product']?->id,
                productReferenced: $meta['productReferenced'],
                product: $meta['product'],
                description: $meta['description'],
                unit: $meta['unit'],
                quantity: $meta['quantity'],
                unitPrice: $meta['unitPrice'],
                discountPercent: $meta['discountPercent'],
                taxes: $meta['taxes'],
                calculated: $calculatedLine,
            );
        }

        return new ValidationContext(
            company: $company,
            customer: $customer,
            lines: $lineContexts,
            calculation: $calculation,
            paymentType: $data['payment_type'] ?? null,
            issueDate: isset($data['issue_date']) ? Carbon::parse($data['issue_date']) : null,
            dueDate: isset($data['due_date']) ? Carbon::parse($data['due_date']) : null,
        );
    }

    /**
     * Builds the same ValidationContext as build(), but from a real
     * persisted draft instead of a raw request array — used by the real
     * wizard (Fase 4) and by IssueInvoiceService. Each item's own current
     * itemTaxes is always used as-is (never re-derived from the product's
     * live tax list), since a persisted line is itself the source of
     * truth once saved.
     */
    public function buildFromInvoice(Invoice $invoice): ValidationContext
    {
        $invoice->loadMissing('items.itemTaxes');

        $data = [
            'customer_id' => $invoice->customer_id,
            'payment_type' => $invoice->payment_type,
            'issue_date' => optional($invoice->issue_date)->toDateString(),
            'due_date' => optional($invoice->due_date)->toDateString(),
            'items' => $invoice->items->map(static fn (InvoiceItem $item): array => [
                'product_service_id' => $item->product_service_id,
                'description' => $item->description,
                'unit' => $item->unit,
                'quantity' => (string) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'discount_percent' => (string) $item->discount_percent,
                'tax_ids' => $item->itemTaxes->pluck('tax_id')->filter()->values()->all(),
            ])->all(),
        ];

        return $this->build($invoice->company, $data);
    }

    private function numericString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        return (string) $value;
    }
}
