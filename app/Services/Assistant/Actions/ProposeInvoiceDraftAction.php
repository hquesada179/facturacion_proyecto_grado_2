<?php

namespace App\Services\Assistant\Actions;

use App\Models\Invoice;
use App\Models\ProductService;
use App\Models\User;
use App\Services\Assistant\Tools\FindProductTool;
use App\Services\Assistant\Tools\GetCustomerInvoicesTool;
use App\Services\Invoices\Calculation\InvoiceCalculator;
use App\Services\Invoices\Calculation\InvoiceDraftInput;
use App\Services\Invoices\Calculation\InvoiceLineInput;
use App\Services\Invoices\Calculation\InvoiceLineTaxInput;
use App\Support\ReportFormatter;
use Illuminate\Support\Facades\Gate;

/**
 * Interpretation + resolution + pure preview for "Prepárame una factura
 * para kata con 2 camisas" — NEVER persists anything (no Invoice/
 * InvoiceItem row is created here). Every number in the returned
 * preview comes straight out of InvoiceCalculator, the exact same
 * domain service the real invoice draft screen uses — this class only
 * ever resolves WHICH customer/products/quantities to feed it.
 */
class ProposeInvoiceDraftAction
{
    public function __construct(
        private readonly GetCustomerInvoicesTool $customers,
        private readonly FindProductTool $products,
        private readonly InvoiceCalculator $calculator,
    ) {}

    /**
     * @param  array{customer_term: ?string, lines: array<int, array{quantity: string, term: string}>, payment_type: ?string, payment_method: ?string, notes: ?string}  $intent
     * @return array<string, mixed>
     */
    public function handle(User $user, array $intent): array
    {
        if (Gate::forUser($user)->denies('create', Invoice::class)) {
            return ['status' => 'denied', 'reason' => 'Tu rol no tiene permiso para preparar facturas.'];
        }

        $customerTerm = $intent['customer_term'];

        if ($customerTerm === null || $customerTerm === '') {
            return ['status' => 'missing', 'missing_fields' => ['cliente'], 'reason' => '¿Para qué cliente es la factura?'];
        }

        $resolution = $this->customers->resolveCustomer($user, $customerTerm);

        if ($resolution['status'] === 'ambiguous') {
            return ['status' => 'ambiguous', 'field' => 'cliente', 'candidates' => $resolution['candidates']];
        }

        if ($resolution['status'] === 'not_found') {
            return [
                'status' => 'missing',
                'missing_fields' => ['cliente'],
                'reason' => "No encontré un cliente que coincida con \"{$customerTerm}\".",
            ];
        }

        $customer = $resolution['customer'];

        if ($intent['lines'] === []) {
            return ['status' => 'missing', 'missing_fields' => ['productos'], 'reason' => '¿Qué productos o servicios lleva la factura?'];
        }

        $resolvedLines = [];

        foreach ($intent['lines'] as $line) {
            $found = $this->resolveProductCandidates($user, $line['term']);
            $totalMatches = $found['total_matches'] ?? 0;

            if ($totalMatches === 0) {
                return [
                    'status' => 'missing',
                    'missing_fields' => ['productos'],
                    'reason' => "No encontré un producto o servicio que coincida con \"{$line['term']}\".",
                ];
            }

            if ($totalMatches > 1) {
                return ['status' => 'ambiguous', 'field' => 'producto', 'candidates' => $found['matches']];
            }

            $product = ProductService::withoutGlobalScopes()
                ->with('taxes')
                ->where('company_id', $user->company_id)
                ->whereKey($found['matches'][0]['id'])
                ->first();

            if ($product === null || Gate::forUser($user)->denies('view', $product)) {
                return [
                    'status' => 'missing',
                    'missing_fields' => ['productos'],
                    'reason' => "No encontré un producto o servicio que coincida con \"{$line['term']}\".",
                ];
            }

            $resolvedLines[] = ['product' => $product, 'quantity' => $line['quantity']];
        }

        [$draftInput, $lineSnapshots] = $this->buildCalculationInput($resolvedLines);
        $result = $this->calculator->calculate($draftInput);

        $payload = [
            'customer_id' => $customer->id,
            'lines' => $lineSnapshots,
            'payment_type' => $intent['payment_type'],
            'payment_method' => $intent['payment_method'],
            'notes' => $intent['notes'],
        ];

        $previewLines = [];
        foreach ($resolvedLines as $index => $resolved) {
            $previewLines[] = [
                'label' => $resolved['product']->name,
                'detail' => '×'.$resolved['quantity'],
                'amount' => ReportFormatter::money((float) $result->lines[$index]->lineTotal),
            ];
        }

        $preview = [
            'title' => 'Borrador de factura',
            'summary' => 'Factura para '.$customer->name.' por '.ReportFormatter::money((float) $result->total).'.',
            'fields' => array_filter([
                $intent['payment_type'] ? ['label' => 'Condición de pago', 'value' => Invoice::PAYMENT_TYPES[$intent['payment_type']] ?? $intent['payment_type']] : null,
                $intent['payment_method'] ? ['label' => 'Método de pago', 'value' => Invoice::PAYMENT_METHODS[$intent['payment_method']] ?? $intent['payment_method']] : null,
                $intent['notes'] ? ['label' => 'Observaciones', 'value' => $intent['notes']] : null,
            ]),
            'lines' => $previewLines,
            'totals' => [
                ['label' => 'Subtotal', 'value' => ReportFormatter::money((float) $result->subtotal)],
                ['label' => 'IVA / impuestos', 'value' => ReportFormatter::money((float) $result->totalTaxes)],
                ['label' => 'Total', 'value' => ReportFormatter::money((float) $result->total)],
            ],
            'warnings' => [],
        ];

        return [
            'status' => 'ready',
            'customer_name' => $customer->name,
            'payload' => $payload,
            'preview' => $preview,
        ];
    }

    /**
     * @param  array<int, array{product: ProductService, quantity: string}>  $resolvedLines
     * @return array{0: InvoiceDraftInput, 1: array<int, array<string, mixed>>}
     */
    private function buildCalculationInput(array $resolvedLines): array
    {
        $lineInputs = [];
        $snapshots = [];

        foreach ($resolvedLines as $resolved) {
            $product = $resolved['product'];
            $taxIds = $product->taxes->pluck('id')->sort()->values()->all();

            $lineInputs[] = new InvoiceLineInput(
                productServiceId: $product->id,
                description: $product->name,
                unit: $product->unit,
                quantity: $resolved['quantity'],
                unitPrice: (string) $product->price,
                discountPercent: '0',
                charges: '0',
                taxes: $product->taxes->map(fn ($tax): InvoiceLineTaxInput => new InvoiceLineTaxInput(
                    taxId: $tax->id,
                    code: $tax->code ?? ('TAX-'.$tax->id),
                    name: $tax->name,
                    rate: (string) $tax->rate,
                ))->all(),
            );

            $snapshots[] = [
                'product_service_id' => $product->id,
                'quantity' => $resolved['quantity'],
                'proposed_unit_price' => (string) $product->price,
                'proposed_tax_ids' => $taxIds,
            ];
        }

        return [new InvoiceDraftInput(lines: $lineInputs, currency: 'COP'), $snapshots];
    }

    /**
     * Safe candidate strategy for a term that doesn't match any product
     * verbatim (bug report: "2 camisas" against a product literally
     * named "camisa"): try the term as typed first; only if that finds
     * NOTHING, retry with a small set of simple Spanish singular/plural
     * flexions of that SAME term (never a different word, never a
     * fuzzy/typo correction — see AssistantMessageNormalizer for that
     * separate, intent-vocabulary-only mechanism). Returns the first
     * non-empty result (0, 1, or 2+ matches) so the caller's existing
     * ambiguous/missing handling applies unchanged — this never itself
     * picks a product, it only decides which search terms to try.
     *
     * @return array<string, mixed>
     */
    private function resolveProductCandidates(User $user, string $term): array
    {
        $found = $this->products->handle($user, $term, [], 5);

        if (($found['total_matches'] ?? 0) > 0) {
            return $found;
        }

        foreach ($this->singularPluralVariants($term) as $variant) {
            $retry = $this->products->handle($user, $variant, [], 5);

            if (($retry['total_matches'] ?? 0) > 0) {
                return $retry;
            }
        }

        return $found;
    }

    /**
     * @return array<int, string>
     */
    private function singularPluralVariants(string $term): array
    {
        $lower = mb_strtolower($term);
        $variants = [];

        if (mb_strlen($lower) > 3 && str_ends_with($lower, 's')) {
            // camisas -> camisa, zapatos -> zapato (vowel-ending plural).
            $variants[] = mb_substr($term, 0, -1);

            if (mb_strlen($lower) > 4 && str_ends_with($lower, 'es')) {
                // relojes -> reloj (consonant-ending plural).
                $variants[] = mb_substr($term, 0, -2);
            }
        } else {
            // The user said it in singular but the catalog might have it
            // in plural — try both common pluralizations.
            $variants[] = $term.'s';
            $variants[] = $term.'es';
        }

        return $variants;
    }
}
