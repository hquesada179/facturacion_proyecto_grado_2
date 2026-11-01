<?php

namespace App\Services\Assistant\Providers;

use App\Services\Assistant\AssistantResponse;
use App\Services\Assistant\Contracts\AiProviderInterface;
use App\Support\ReportFormatter;

class LocalFallbackProvider implements AiProviderInterface
{
    public function generate(array $context, string $message, array $toolResults = []): AssistantResponse
    {
        $prefix = 'El proveedor de IA externo no está configurado. Puedo seguir ayudándote con información estructurada del sistema.';

        if ($tool = $this->firstTool($toolResults, 'explain_validation')) {
            return new AssistantResponse($prefix."\n\n".$this->validationMessage($tool));
        }

        if ($tool = $this->firstTool($toolResults, 'invoice_details')) {
            return new AssistantResponse($prefix."\n\n".$this->invoiceMessage($tool));
        }

        if ($tool = $this->firstTool($toolResults, 'traceability')) {
            return new AssistantResponse($prefix."\n\n".$this->traceabilityMessage($tool));
        }

        // Credit-note-specific results are checked ahead of find_invoice:
        // a message that explicitly says "nota crédito" (e.g. "¿qué nota
        // crédito anuló FV-000002?") should answer about the credit note,
        // even though the FV-number regex also incidentally looked up the
        // invoice itself.
        if ($tool = $this->firstTool($toolResults, 'credit_note_details')) {
            return new AssistantResponse($prefix."\n\n".$this->creditNoteMessage($tool));
        }

        if ($tool = $this->firstTool($toolResults, 'search_credit_notes')) {
            return new AssistantResponse($prefix."\n\n".$this->searchCreditNotesMessage($tool));
        }

        if ($tool = $this->firstTool($toolResults, 'customer_documents')) {
            return new AssistantResponse($prefix."\n\n".$this->customerDocumentsMessage($tool));
        }

        if ($tool = $this->firstTool($toolResults, 'find_invoice')) {
            return new AssistantResponse($prefix."\n\n".$this->findInvoiceMessage($tool));
        }

        if ($tool = $this->firstTool($toolResults, 'find_customer')) {
            return new AssistantResponse($prefix."\n\n".$this->customerMessage($tool));
        }

        if ($tool = $this->firstTool($toolResults, 'find_product')) {
            return new AssistantResponse($prefix."\n\n".$this->productMessage($tool));
        }

        if ($tool = $this->firstTool($toolResults, 'document_counts')) {
            return new AssistantResponse($prefix."\n\n".$this->documentCountsMessage($tool));
        }

        if ($tool = $this->firstTool($toolResults, 'billing_summary')) {
            return new AssistantResponse($prefix."\n\n".$this->billingSummaryMessage($tool));
        }

        if ($tool = $this->firstTool($toolResults, 'document_errors')) {
            return new AssistantResponse($prefix."\n\n".$this->documentListMessage($tool, 'No encontré documentos en error o rechazados.', 'Documentos con error o rechazados'));
        }

        if ($tool = $this->firstTool($toolResults, 'pending_documents')) {
            return new AssistantResponse($prefix."\n\n".$this->documentListMessage($tool, 'No encontré documentos pendientes.', 'Documentos pendientes'));
        }

        return new AssistantResponse(
            $prefix."\n\nPuedes pedirme buscar una factura, explicar validaciones, revisar impuestos, consultar trazabilidad, ubicar clientes/productos o revisar notas crédito.",
            [
                ['label' => 'Buscar factura', 'message' => 'Busca la factura FV-000001'],
                ['label' => 'Explicar errores', 'message' => '¿Por qué no puedo emitir esta factura?'],
                ['label' => 'Consultar trazabilidad', 'message' => '¿Qué pasó con esta factura?'],
            ],
        );
    }

    public function name(): string
    {
        return 'local_fallback';
    }

    public function modelIdentifier(): string
    {
        return 'deterministic-fallback';
    }

    private function firstTool(array $toolResults, string $name): ?array
    {
        foreach ($toolResults as $toolResult) {
            if (($toolResult['tool'] ?? null) === $name) {
                return $toolResult;
            }
        }

        return null;
    }

    private function validationMessage(array $tool): string
    {
        if (! ($tool['found'] ?? true)) {
            return 'No encontré esa factura dentro de tu empresa.';
        }

        $blocking = (int) ($tool['blocking_count'] ?? 0);
        $warnings = (int) ($tool['warning_count'] ?? 0);

        if ($blocking === 0 && $warnings === 0) {
            return 'Según el ValidationEngine del sistema, la factura no tiene bloqueos ni advertencias.';
        }

        $lines = ["Según el ValidationEngine del sistema, la factura tiene {$blocking} bloqueo(s) y {$warnings} advertencia(s)."];

        foreach (($tool['results'] ?? []) as $result) {
            $lines[] = '- '.$result['code'].': '.$result['message'].' Campo: '.$result['field'].'. Sugerencia: '.$result['suggestion'];
        }

        return implode("\n", $lines);
    }

    private function invoiceMessage(array $tool): string
    {
        if (! ($tool['found'] ?? true)) {
            return 'No encontré ese documento dentro de tu empresa.';
        }

        $invoice = $tool['invoice'];
        $lines = [
            'Información proveniente del sistema:',
            '- Factura: '.($invoice['number'] ?? 'Borrador #'.$invoice['id']),
            '- Cliente: '.($invoice['customer'] ?? 'Sin cliente'),
            '- Estado: '.$invoice['status_label'],
            '- Subtotal: '.ReportFormatter::money($invoice['subtotal']),
            '- IVA/impuestos: '.ReportFormatter::money($invoice['tax_total']),
            '- Total: '.ReportFormatter::money($invoice['total']),
            '- Notas crédito asociadas: '.$invoice['credit_notes_count'],
        ];

        foreach (($invoice['tax_breakdown'] ?? []) as $tax) {
            $lines[] = '- Cálculo impuesto: base '.ReportFormatter::money($tax['base']).' x '.$tax['rate'].'% = '.ReportFormatter::money($tax['tax']);
        }

        return implode("\n", $lines);
    }

    private function findInvoiceMessage(array $tool): string
    {
        if (empty($tool['matches'])) {
            return 'No encontré ese documento dentro de tu empresa.';
        }

        $matches = collect($tool['matches'])
            ->map(fn (array $invoice): string => ($invoice['number'] ?? 'Borrador #'.$invoice['id']).' · '.$invoice['status_label'].' · '.ReportFormatter::money($invoice['total']))
            ->implode("\n");

        $total = (int) ($tool['total_matches'] ?? count($tool['matches']));
        $showing = count($tool['matches']);
        $suffix = $total > $showing ? "\n\nMostrando {$showing} de {$total} resultados." : '';

        return "Encontré estas facturas dentro de tu empresa:\n".$matches.$suffix;
    }

    private function traceabilityMessage(array $tool): string
    {
        if (! ($tool['allowed'] ?? true)) {
            return 'Tu rol no tiene permiso para consultar la trazabilidad detallada.';
        }

        if (empty($tool['events'])) {
            return 'No hay eventos de trazabilidad registrados para ese documento.';
        }

        $events = collect($tool['events'])
            ->map(fn (array $event): string => '- '.$event['date'].': '.$event['description'].' ('.$event['type'].') '.$event['from_status'].' -> '.$event['to_status'])
            ->implode("\n");

        return "Trazabilidad registrada por el sistema:\n".$events;
    }

    private function customerMessage(array $tool): string
    {
        if (empty($tool['matches'])) {
            return 'No encontré ese cliente dentro de tu empresa.';
        }

        return collect($tool['matches'])
            ->map(fn (array $customer): string => 'Dato registrado: '.$customer['name'].' · '.$customer['identification'].' · documentos: '.$customer['documents_count'])
            ->implode("\n");
    }

    private function productMessage(array $tool): string
    {
        if (empty($tool['matches'])) {
            return 'No encontré ese producto o servicio dentro de tu empresa.';
        }

        return collect($tool['matches'])
            ->map(fn (array $product): string => 'Dato registrado del catálogo: '.$product['sku'].' · '.$product['name'].' · '.$product['status'].' · precio '.ReportFormatter::money($product['price']).' · impuestos '.$product['taxes'])
            ->implode("\n");
    }

    private function creditNoteMessage(array $tool): string
    {
        if (! ($tool['found'] ?? true)) {
            return 'No encontré esa nota crédito dentro de tu empresa.';
        }

        $note = $tool['credit_note'];

        return implode("\n", [
            'Información proveniente del sistema:',
            '- Nota crédito: '.($note['number'] ?? 'Borrador NC #'.$note['id']),
            '- Factura origen: '.$note['invoice_number'],
            '- Estado: '.$note['status_label'],
            '- Motivo: '.$note['reason'],
            '- Total acreditado: '.ReportFormatter::money($note['total']),
        ]);
    }

    private function customerDocumentsMessage(array $tool): string
    {
        if ($tool['ambiguous'] ?? false) {
            $names = collect($tool['candidates'] ?? [])
                ->map(fn (array $customer): string => $customer['name'].' ('.$customer['identification'].')')
                ->implode(', ');

            return "Encontré varios clientes que coinciden con tu búsqueda: {$names}. Dime cuál es, o usa el nombre completo o el documento.";
        }

        if (! ($tool['found'] ?? false)) {
            return 'No encontré ningún cliente que coincida con esa búsqueda dentro de tu empresa.';
        }

        $customerName = $tool['customer']['name'] ?? 'Cliente';
        $lines = ["Documentos de {$customerName} (datos reales del sistema):"];

        if (empty($tool['invoices']) && empty($tool['credit_notes'])) {
            $lines[] = 'No tiene facturas ni notas crédito registradas todavía.';

            return implode("\n", $lines);
        }

        foreach ($tool['invoices'] ?? [] as $invoice) {
            $lines[] = '- Factura '.$invoice['number'].': '.$invoice['status_label'].' · '.ReportFormatter::money($invoice['total']);
        }

        foreach ($tool['credit_notes'] ?? [] as $creditNote) {
            $lines[] = '- Nota crédito '.$creditNote['number'].': '.$creditNote['status_label'].' · '.ReportFormatter::money($creditNote['total']);
        }

        $totals = $tool['totals'] ?? null;

        if ($totals) {
            $lines[] = 'Total facturado: '.ReportFormatter::money($totals['billed']).' · Total acreditado: '.ReportFormatter::money($totals['credited']).' · Neto documental: '.ReportFormatter::money($totals['net']);
        }

        return implode("\n", $lines);
    }

    private function searchCreditNotesMessage(array $tool): string
    {
        if (empty($tool['matches'])) {
            return 'No encontré notas crédito que coincidan con esa búsqueda dentro de tu empresa.';
        }

        $matches = collect($tool['matches'])
            ->map(fn (array $creditNote): string => $creditNote['number'].' · factura '.($creditNote['invoice_number'] ?? 'N/A').' · '.$creditNote['status_label'].' · '.ReportFormatter::money($creditNote['total']))
            ->implode("\n");

        $total = (int) ($tool['total_matches'] ?? count($tool['matches']));
        $showing = count($tool['matches']);
        $suffix = $total > $showing ? "\n\nMostrando {$showing} de {$total} resultados." : '';

        return "Encontré estas notas crédito dentro de tu empresa:\n".$matches.$suffix;
    }

    private function documentCountsMessage(array $tool): string
    {
        if (! ($tool['allowed'] ?? false)) {
            return 'Tu rol no tiene permiso para consultar estos conteos.';
        }

        $invoices = $tool['invoices_by_status'] ?? [];
        $creditNotes = $tool['credit_notes_by_status'] ?? [];
        $scopeNote = $tool['scope'] === 'own' ? ' (solo tus propios documentos)' : ' (de toda la empresa)';

        return implode("\n", [
            "Conteos reales del sistema{$scopeNote}:",
            '- Facturas totales: '.$tool['invoices_total'],
            '- Facturas emitidas: '.($invoices['issued'] ?? 0),
            '- Facturas anuladas: '.($invoices['voided'] ?? 0),
            '- Borradores: '.($invoices['draft'] ?? 0),
            '- Parcialmente abonadas: '.($invoices['partially_credited'] ?? 0),
            '- Con error técnico: '.($invoices['technical_error'] ?? 0),
            '- Notas crédito emitidas: '.($creditNotes['issued'] ?? 0),
            '- Notas crédito con error: '.($creditNotes['technical_error'] ?? 0),
            '- Clientes: '.$tool['customers_total'],
            '- Productos activos: '.$tool['products_active'],
        ]);
    }

    private function billingSummaryMessage(array $tool): string
    {
        if (! ($tool['allowed'] ?? false)) {
            return 'Tu rol no tiene permiso para consultar el resumen financiero.';
        }

        $summary = $tool['summary'];

        return implode("\n", [
            'Resumen financiero — '.$tool['period_label'].' (datos de DocumentReportService):',
            '- Facturación bruta: '.ReportFormatter::money($summary['gross']),
            '- IVA / impuestos: '.ReportFormatter::money($summary['tax_total']),
            '- Valor acreditado: '.ReportFormatter::money($summary['credited']),
            '- Neto documental: '.ReportFormatter::money($summary['net']),
            '- Facturas emitidas: '.$summary['emitted'],
            '- Facturas anuladas: '.$summary['voided'],
            '- Rechazadas o con error: '.$summary['rejected'],
        ]);
    }

    private function documentListMessage(array $tool, string $emptyMessage, string $title): string
    {
        if (! ($tool['allowed'] ?? false)) {
            return 'Tu rol no tiene permiso para consultar esta información.';
        }

        if (empty($tool['documents'])) {
            return $emptyMessage;
        }

        $lines = ["{$title}:"];

        foreach ($tool['documents'] as $document) {
            $label = $document['type'] === 'invoice' ? 'Factura' : 'Nota crédito';
            $lines[] = '- '.$label.' '.$document['number'].': '.$document['status_label'].' · '.ReportFormatter::money($document['total'] ?? 0);
        }

        $total = (int) ($tool['total_matches'] ?? count($tool['documents']));
        $showing = count($tool['documents']);

        if ($total > $showing) {
            $lines[] = "Mostrando {$showing} de {$total} resultados.";
        }

        return implode("\n", $lines);
    }
}
