<?php

namespace App\Services\Assistant;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Models\User;
use App\Support\ReportFormatter;
use Illuminate\Support\Facades\Gate;

/**
 * Maps already-fetched, already-authorized tool results into the
 * structured `components` the frontend renders as cards/lists.
 *
 * This class never queries the database and never trusts anything from
 * the model — every field it reads was already produced by a Tool class
 * under the user's own Gate/company_id checks. It only re-derives
 * server-side routes (`route()`, never a URL supplied by the model or the
 * AI provider) and the small set of *additional* per-item policy checks
 * needed for optional PDF/trazabilidad links. Those extra checks reuse
 * the real authorization rule directly (InvoiceStatus::wasIssued(),
 * CreditNoteStatus::Issued) rather than re-querying each row — the
 * company_id match is already guaranteed transitively because every item
 * here already passed a `Gate::allows('view', ...)` check inside its Tool.
 */
class AssistantComponentBuilder
{
    private const MAX_ITEMS = 10;

    /**
     * @param  array<int, array<string, mixed>>  $toolResults
     * @return array<int, array<string, mixed>>
     */
    public function build(User $user, array $toolResults): array
    {
        $canViewTraceability = Gate::forUser($user)->allows('view-traceability');
        $components = [];

        foreach ($toolResults as $result) {
            $built = match ($result['tool'] ?? null) {
                'find_invoice' => [$this->invoiceList($canViewTraceability, $result)],
                'search_credit_notes' => [$this->creditNoteList($result)],
                'find_customer' => [$this->customerList($result)],
                'find_product' => [$this->productList($result)],
                'traceability' => array_filter([$this->traceabilitySummary($result)]),
                'document_counts' => array_filter([$this->countsSummary($result)]),
                'billing_summary' => array_filter([$this->billingSummary($result)]),
                'document_errors' => $this->mixedToListComponents($canViewTraceability, $result),
                'pending_documents' => $this->mixedToListComponents($canViewTraceability, $result),
                'customer_documents' => $this->customerDocuments($canViewTraceability, $result),
                'ranking' => array_filter([$this->rankingSummary($result)]),
                default => [],
            };

            array_push($components, ...array_values($built));
        }

        return $components;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function invoiceList(bool $canViewTraceability, array $result): array
    {
        $matches = $result['matches'] ?? [];

        $items = array_map(function (array $invoice) use ($canViewTraceability): array {
            $wasIssued = (InvoiceStatus::tryFrom($invoice['status'] ?? '')?->wasIssued()) ?? false;
            $number = $invoice['number'] ?? null;

            return [
                'id' => $invoice['id'],
                'number' => $number,
                'customer' => $invoice['customer'] ?? null,
                'status' => $invoice['status'] ?? null,
                'status_label' => $invoice['status_label'] ?? null,
                'total' => $invoice['total'] ?? null,
                'date' => $invoice['issued_at'] ?? null,
                'url' => route('invoices.show', $invoice['id']),
                'pdf_url' => $wasIssued ? route('invoices.pdf.show', $invoice['id']) : null,
                'traceability_url' => ($canViewTraceability && $number)
                    ? route('traceability.index', ['q' => $number])
                    : null,
            ];
        }, array_slice($matches, 0, self::MAX_ITEMS));

        return [
            'type' => 'invoice_list',
            'items' => $items,
            'total_matches' => $result['total_matches'] ?? count($items),
            'showing' => count($items),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function creditNoteList(array $result): array
    {
        $matches = $result['matches'] ?? [];

        $items = array_map(fn (array $creditNote): array => [
            'id' => $creditNote['id'],
            'number' => $creditNote['number'] ?? null,
            'invoice_number' => $creditNote['invoice_number'] ?? null,
            'customer' => $creditNote['customer'] ?? null,
            'status' => $creditNote['status'] ?? null,
            'status_label' => $creditNote['status_label'] ?? null,
            'total' => $creditNote['total'] ?? null,
            'date' => $creditNote['date'] ?? null,
            'url' => route('credit-notes.show', $creditNote['id']),
            'pdf_url' => ($creditNote['status'] ?? null) === CreditNoteStatus::Issued->value
                ? route('credit-notes.pdf.show', $creditNote['id'])
                : null,
        ], array_slice($matches, 0, self::MAX_ITEMS));

        return [
            'type' => 'credit_note_list',
            'items' => $items,
            'total_matches' => $result['total_matches'] ?? count($items),
            'showing' => count($items),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function customerList(array $result): array
    {
        $matches = $result['matches'] ?? $result['candidates'] ?? [];

        $items = array_map(fn (array $customer): array => [
            'id' => $customer['id'],
            'name' => $customer['name'],
            'identification' => $customer['identification'] ?? null,
            'status' => $customer['status'] ?? null,
            'documents_count' => $customer['documents_count'] ?? null,
            'url' => route('customers.show', $customer['id']),
        ], array_slice($matches, 0, self::MAX_ITEMS));

        return [
            'type' => 'customer_list',
            'items' => $items,
            'total_matches' => $result['total_matches'] ?? count($items),
            'showing' => count($items),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function productList(array $result): array
    {
        $matches = $result['matches'] ?? [];

        $items = array_map(fn (array $product): array => [
            'id' => $product['id'],
            'sku' => $product['sku'] ?? null,
            'name' => $product['name'] ?? null,
            'price' => $product['price'] ?? null,
            'status' => $product['status'] ?? null,
            'taxes' => $product['taxes'] ?? null,
            'url' => route('products.show', $product['id']),
        ], array_slice($matches, 0, self::MAX_ITEMS));

        return [
            'type' => 'product_list',
            'items' => $items,
            'total_matches' => $result['total_matches'] ?? count($items),
            'showing' => count($items),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function traceabilitySummary(array $result): ?array
    {
        if (! ($result['found'] ?? false)) {
            return null;
        }

        $events = array_slice($result['events'] ?? [], 0, 20);

        return [
            'type' => 'traceability_summary',
            'invoice_number' => $result['invoice_number'] ?? null,
            'items' => $events,
            'total_events' => $result['total_events'] ?? count($events),
            'showing' => count($events),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function rankingSummary(array $result): ?array
    {
        if (! ($result['allowed'] ?? false) || empty($result['items'])) {
            return null;
        }

        $isProducts = $result['type'] === 'products';

        return [
            'type' => 'metric_summary',
            'title' => ($isProducts ? 'Productos' : 'Clientes').' con más facturación — '.($result['period_label'] ?? ''),
            'items' => array_map(fn (array $row): array => [
                'label' => $isProducts ? ($row['description'] ?? $row['code'] ?? 'Producto') : ($row['customer'] ?? 'Cliente'),
                'value' => ReportFormatter::money($isProducts ? ($row['invoiced_total'] ?? 0) : ($row['net_total'] ?? $row['invoiced_total'] ?? 0)),
            ], array_slice($result['items'], 0, 5)),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function countsSummary(array $result): ?array
    {
        if (! ($result['allowed'] ?? false)) {
            return null;
        }

        $invoicesByStatus = $result['invoices_by_status'] ?? [];
        $creditNotesByStatus = $result['credit_notes_by_status'] ?? [];

        return [
            'type' => 'metric_summary',
            'title' => 'Resumen de documentos',
            'items' => [
                ['label' => 'Facturas totales', 'value' => (string) ($result['invoices_total'] ?? 0)],
                ['label' => 'Facturas emitidas', 'value' => (string) ($invoicesByStatus['issued'] ?? 0)],
                ['label' => 'Facturas anuladas', 'value' => (string) ($invoicesByStatus['voided'] ?? 0)],
                ['label' => 'Borradores', 'value' => (string) ($invoicesByStatus['draft'] ?? 0)],
                ['label' => 'Parcialmente abonadas', 'value' => (string) ($invoicesByStatus['partially_credited'] ?? 0)],
                ['label' => 'Con error técnico', 'value' => (string) ($invoicesByStatus['technical_error'] ?? 0)],
                ['label' => 'Notas crédito emitidas', 'value' => (string) ($creditNotesByStatus['issued'] ?? 0)],
                ['label' => 'Notas crédito con error', 'value' => (string) ($creditNotesByStatus['technical_error'] ?? 0)],
                ['label' => 'Clientes', 'value' => (string) ($result['customers_total'] ?? 0)],
                ['label' => 'Productos activos', 'value' => (string) ($result['products_active'] ?? 0)],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function billingSummary(array $result): ?array
    {
        if (! ($result['allowed'] ?? false)) {
            return null;
        }

        $summary = $result['summary'] ?? [];

        return [
            'type' => 'metric_summary',
            'title' => 'Resumen financiero — '.($result['period_label'] ?? ''),
            'items' => [
                ['label' => 'Facturación bruta', 'value' => ReportFormatter::money($summary['gross'] ?? 0)],
                ['label' => 'IVA / impuestos', 'value' => ReportFormatter::money($summary['tax_total'] ?? 0)],
                ['label' => 'Valor acreditado', 'value' => ReportFormatter::money($summary['credited'] ?? 0)],
                ['label' => 'Neto documental', 'value' => ReportFormatter::money($summary['net'] ?? 0)],
                ['label' => 'Facturas emitidas', 'value' => (string) ($summary['emitted'] ?? 0)],
                ['label' => 'Facturas anuladas', 'value' => (string) ($summary['voided'] ?? 0)],
                ['label' => 'Rechazadas o con error', 'value' => (string) ($summary['rejected'] ?? 0)],
            ],
        ];
    }

    /**
     * document_errors / pending_documents share the same mixed
     * invoice+credit_note document shape — split by type and reuse the
     * same invoice_list/credit_note_list mapping as any other search.
     *
     * @param  array<string, mixed>  $result
     * @return array<int, array<string, mixed>>
     */
    private function mixedToListComponents(bool $canViewTraceability, array $result): array
    {
        if (! ($result['allowed'] ?? false)) {
            return [];
        }

        $documents = $result['documents'] ?? [];
        $totalMatches = $result['total_matches'] ?? count($documents);

        $invoices = array_values(array_filter($documents, fn (array $doc): bool => $doc['type'] === 'invoice'));
        $creditNotes = array_values(array_filter($documents, fn (array $doc): bool => $doc['type'] === 'credit_note'));

        $components = [];

        if ($invoices !== []) {
            $components[] = $this->invoiceList($canViewTraceability, [
                'matches' => array_map(fn (array $doc): array => [
                    'id' => $doc['id'],
                    'number' => $doc['number'],
                    'customer' => $doc['customer'],
                    'status' => $doc['status'],
                    'status_label' => $doc['status_label'],
                    'total' => $doc['total'] ?? 0.0,
                    'issued_at' => $doc['date'] ?? null,
                ], $invoices),
                'total_matches' => $totalMatches,
            ]);
        }

        if ($creditNotes !== []) {
            $components[] = $this->creditNoteList([
                'matches' => array_map(fn (array $doc): array => [
                    'id' => $doc['id'],
                    'number' => $doc['number'],
                    'invoice_number' => null,
                    'customer' => $doc['customer'],
                    'status' => $doc['status'],
                    'status_label' => $doc['status_label'],
                    'total' => $doc['total'] ?? 0.0,
                    'date' => $doc['date'] ?? null,
                ], $creditNotes),
                'total_matches' => $totalMatches,
            ]);
        }

        return $components;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<int, array<string, mixed>>
     */
    private function customerDocuments(bool $canViewTraceability, array $result): array
    {
        if ($result['ambiguous'] ?? false) {
            $candidates = $result['candidates'] ?? [];

            return [$this->customerList(['matches' => $candidates, 'total_matches' => count($candidates)])];
        }

        if (! ($result['found'] ?? false)) {
            return [];
        }

        $customerName = $result['customer']['name'] ?? null;
        $components = [];

        if (! empty($result['invoices'])) {
            $components[] = $this->invoiceList($canViewTraceability, [
                'matches' => array_map(fn (array $invoice): array => [
                    'id' => $invoice['id'],
                    'number' => $invoice['number'],
                    'customer' => $customerName,
                    'status' => $invoice['status'],
                    'status_label' => $invoice['status_label'],
                    'total' => $invoice['total'],
                    'issued_at' => $invoice['date'] ?? null,
                ], $result['invoices']),
                'total_matches' => $result['invoices_total_matches'] ?? count($result['invoices']),
            ]);
        }

        if (! empty($result['credit_notes'])) {
            $components[] = $this->creditNoteList([
                'matches' => array_map(fn (array $creditNote): array => [
                    'id' => $creditNote['id'],
                    'number' => $creditNote['number'],
                    'invoice_number' => $creditNote['invoice_number'],
                    'customer' => $customerName,
                    'status' => $creditNote['status'],
                    'status_label' => $creditNote['status_label'],
                    'total' => $creditNote['total'],
                    'date' => $creditNote['date'] ?? null,
                ], $result['credit_notes']),
                'total_matches' => $result['credit_notes_total_matches'] ?? count($result['credit_notes']),
            ]);
        }

        $totals = $result['totals'] ?? null;

        if ($totals && ($totals['billed'] > 0 || $totals['credited'] > 0)) {
            $components[] = [
                'type' => 'metric_summary',
                'title' => 'Resumen de '.($customerName ?? 'cliente'),
                'items' => [
                    ['label' => 'Total facturado', 'value' => ReportFormatter::money($totals['billed'])],
                    ['label' => 'Total acreditado', 'value' => ReportFormatter::money($totals['credited'])],
                    ['label' => 'Neto documental', 'value' => ReportFormatter::money($totals['net'])],
                ],
            ];
        }

        return $components;
    }
}
