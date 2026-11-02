<?php

namespace App\Services\Assistant\Actions;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\CreditNotes\CreditNoteValidationService;
use App\Services\Invoices\Calculation\InvoiceCalculator;
use App\Services\Invoices\Calculation\InvoiceDraftInput;
use App\Services\Invoices\Calculation\InvoiceLineInput;
use App\Services\Invoices\Calculation\InvoiceLineTaxInput;
use App\Support\ReportFormatter;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Gate;

/**
 * Interpretation + resolution + pure preview for "Prepárame una nota
 * crédito total para FV-000003" — never saves a CreditNote row, never
 * validates it (CreditNoteValidationService here is only used to read
 * the current acreditable balance, not to transition any status) and
 * never issues it. Totals come from InvoiceCalculator fed with each
 * original invoice line's own stored price/tax snapshot — the exact
 * figures a real credit note would carry — never a number the model
 * invented.
 */
class ProposeCreditNoteDraftAction
{
    public function __construct(
        private readonly CreditNoteValidationService $availability,
        private readonly InvoiceCalculator $calculator,
    ) {}

    /**
     * @param  array{invoice_number: ?string, total: bool, lines: array<int, array{quantity: string, term: string}>, bare_quantity: ?string}  $intent
     * @return array<string, mixed>
     */
    public function handle(User $user, array $intent): array
    {
        if ($intent['invoice_number'] === null) {
            return [
                'status' => 'missing',
                'missing_fields' => ['factura'],
                'reason' => '¿Para qué factura es la nota crédito?',
            ];
        }

        $invoice = Invoice::withoutGlobalScopes()
            ->with('items.itemTaxes')
            ->where('company_id', $user->company_id)
            ->where('number', $intent['invoice_number'])
            ->first();

        if ($invoice === null || Gate::forUser($user)->denies('view', $invoice)) {
            return [
                'status' => 'missing',
                'missing_fields' => ['factura'],
                'reason' => "No encontré la factura {$intent['invoice_number']} dentro de tu empresa.",
            ];
        }

        if (Gate::forUser($user)->denies('create', [CreditNote::class, $invoice])) {
            return [
                'status' => 'denied',
                'reason' => $invoice->status->canReceiveCreditNote()
                    ? 'Tu rol no tiene permiso para preparar notas crédito.'
                    : "La factura {$invoice->number} no puede recibir una nota crédito (estado: {$invoice->status->label()}).",
            ];
        }

        $selected = $this->selectItems($invoice, $intent);

        if ($selected['status'] !== 'ready') {
            return $selected;
        }

        $lines = $selected['lines'];
        $reasonCode = $intent['total'] ? 'PROTO_TOTAL_VOID' : 'PROTO_PARTIAL_RETURN';
        $reasonText = $intent['total']
            ? 'Anulación total solicitada vía asistente, tras confirmación humana.'
            : 'Devolución parcial solicitada vía asistente, tras confirmación humana.';

        [$lineInputs, $itemsPayload] = $this->buildCalculationInput($lines);
        $result = $this->calculator->calculate(new InvoiceDraftInput(lines: $lineInputs, currency: $invoice->currency ?? 'COP'));

        $previewLines = [];
        foreach ($lines as $index => $line) {
            $previewLines[] = [
                'label' => $line['item']->description,
                'detail' => '×'.$line['quantity'],
                'amount' => ReportFormatter::money((float) $result->lines[$index]->lineTotal),
            ];
        }

        $preview = [
            'title' => 'Preparar nota crédito',
            'summary' => 'Nota crédito para la factura '.$invoice->number.' por '.ReportFormatter::money((float) $result->total).'.',
            'fields' => [
                ['label' => 'Factura origen', 'value' => $invoice->number],
                ['label' => 'Cliente', 'value' => $invoice->customer?->name ?? 'Sin cliente'],
                ['label' => 'Motivo', 'value' => CreditNote::REASONS[$reasonCode]],
            ],
            'lines' => $previewLines,
            'totals' => [
                ['label' => 'Subtotal', 'value' => ReportFormatter::money((float) $result->subtotal)],
                ['label' => 'IVA / impuestos', 'value' => ReportFormatter::money((float) $result->totalTaxes)],
                ['label' => 'Total acreditado', 'value' => ReportFormatter::money((float) $result->total)],
            ],
            'warnings' => [],
        ];

        $payload = [
            'invoice_id' => $invoice->id,
            'reason_code' => $reasonCode,
            'reason_text' => $reasonText,
            'items' => $itemsPayload,
        ];

        return ['status' => 'ready', 'payload' => $payload, 'preview' => $preview];
    }

    /**
     * @return array<string, mixed>
     */
    private function selectItems(Invoice $invoice, array $intent): array
    {
        if ($intent['total']) {
            $lines = [];

            foreach ($invoice->items as $item) {
                $available = $this->availability->availableQuantity($item);

                if (BigDecimal::of($available)->isGreaterThan(BigDecimal::zero())) {
                    $lines[] = ['item' => $item, 'quantity' => $available];
                }
            }

            if ($lines === []) {
                return ['status' => 'invalid', 'reason' => "La factura {$invoice->number} ya no tiene saldo disponible para acreditar."];
            }

            return ['status' => 'ready', 'lines' => $lines];
        }

        if ($intent['lines'] !== []) {
            $lines = [];

            foreach ($intent['lines'] as $line) {
                $matches = $invoice->items->filter(
                    fn (InvoiceItem $item): bool => str_contains(mb_strtolower($item->description), mb_strtolower($line['term']))
                );

                if ($matches->count() !== 1) {
                    return [
                        'status' => 'missing',
                        'missing_fields' => ['línea de factura'],
                        'reason' => $matches->isEmpty()
                            ? "No encontré una línea de la factura {$invoice->number} que coincida con \"{$line['term']}\"."
                            : "Hay más de una línea de la factura {$invoice->number} que coincide con \"{$line['term']}\". Sé más específico.",
                    ];
                }

                $item = $matches->first();
                $available = $this->availability->availableQuantity($item);

                if (BigDecimal::of($line['quantity'])->isGreaterThan(BigDecimal::of($available))) {
                    return [
                        'status' => 'invalid',
                        'reason' => "La cantidad solicitada de \"{$item->description}\" supera el saldo disponible ({$available}).",
                    ];
                }

                $lines[] = ['item' => $item, 'quantity' => $line['quantity']];
            }

            return ['status' => 'ready', 'lines' => $lines];
        }

        if ($intent['bare_quantity'] !== null && $invoice->items->count() === 1) {
            $item = $invoice->items->first();
            $available = $this->availability->availableQuantity($item);

            if (BigDecimal::of($intent['bare_quantity'])->isGreaterThan(BigDecimal::of($available))) {
                return [
                    'status' => 'invalid',
                    'reason' => "La cantidad solicitada supera el saldo disponible ({$available}).",
                ];
            }

            return ['status' => 'ready', 'lines' => [['item' => $item, 'quantity' => $intent['bare_quantity']]]];
        }

        return [
            'status' => 'missing',
            'missing_fields' => ['líneas a acreditar'],
            'reason' => "La factura {$invoice->number} tiene varias líneas. ¿Cuál quieres acreditar y en qué cantidad, o es una anulación total?",
        ];
    }

    /**
     * @param  array<int, array{item: InvoiceItem, quantity: string}>  $lines
     * @return array{0: array<int, InvoiceLineInput>, 1: array<int, array<string, mixed>>}
     */
    private function buildCalculationInput(array $lines): array
    {
        $lineInputs = [];
        $itemsPayload = [];

        foreach ($lines as $line) {
            $item = $line['item'];

            $lineInputs[] = new InvoiceLineInput(
                productServiceId: $item->product_service_id,
                description: $item->description,
                unit: $item->unit,
                quantity: $line['quantity'],
                unitPrice: (string) $item->unit_price,
                discountPercent: (string) $item->discount_percent,
                charges: '0',
                taxes: $item->itemTaxes->map(fn ($tax): InvoiceLineTaxInput => new InvoiceLineTaxInput(
                    taxId: $tax->tax_id,
                    code: $tax->code,
                    name: $tax->name,
                    rate: (string) $tax->rate,
                ))->all(),
            );

            $itemsPayload[$item->id] = [
                'quantity' => $line['quantity'],
                'proposed_available' => $this->availability->availableQuantity($item),
            ];
        }

        return [$lineInputs, $itemsPayload];
    }
}
