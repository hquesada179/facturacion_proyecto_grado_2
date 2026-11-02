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

        // Never invent an entity: this only ever appears alone, when the
        // dispatcher found a referencing phrase ("esa factura", "cuáles")
        // but no fresh, resolvable memory to back it.
        if ($tool = $this->firstTool($toolResults, 'clarification_needed')) {
            return new AssistantResponse($tool['reason'] ?? '¿Puedes darme más detalles?');
        }

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

        if ($tool = $this->firstTool($toolResults, 'ranking')) {
            return new AssistantResponse($prefix."\n\n".$this->rankingMessage($tool));
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
            $lines = ['Según el ValidationEngine del sistema, la factura no tiene bloqueos ni advertencias.'];
        } else {
            $lines = ["Según el ValidationEngine del sistema, la factura tiene {$blocking} bloqueo(s) y {$warnings} advertencia(s)."];

            foreach (($tool['results'] ?? []) as $result) {
                $lines[] = '- '.$result['code'].': '.$result['message'].' Campo: '.$result['field'].'. Sugerencia: '.$result['suggestion'];
            }
        }

        // Missing numbering blocks emission even with zero ValidationEngine
        // blockers — it's checked separately, at emission time — so the
        // assistant only ever mentions it when it's actually true.
        if (! ($tool['has_active_numbering_resolution'] ?? true)) {
            $lines[] = 'No hay una resolución de numeración vigente. Ve a Configuración → Facturación / Numeración y crea una resolución simulada activa antes de emitir.';
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

    /**
     * A single match keeps its detailed one-line answer (no card exists
     * to show that same single value, and several existing tests —
     * including the prompt-injection ones — rely on the entity's own
     * name/number appearing in the text). From two matches up, the
     * per-item breakdown is dropped entirely: that's exactly what the
     * invoice_list/customer_list/etc. component already renders as
     * cards, so repeating it as text would just be the same table twice.
     */
    private function findInvoiceMessage(array $tool): string
    {
        $matches = $tool['matches'] ?? [];

        if (empty($matches)) {
            return 'No encontré facturas que coincidan con esa búsqueda dentro de tu empresa.';
        }

        $total = (int) ($tool['total_matches'] ?? count($matches));

        if ($total === 1) {
            $invoice = $matches[0];

            return 'Encontré la factura '.($invoice['number'] ?? 'Borrador #'.$invoice['id']).': '.$invoice['status_label'].' · '.ReportFormatter::money($invoice['total']).'.';
        }

        $noun = $total === 1 ? 'factura' : 'facturas';

        return "Encontré {$total} {$noun} dentro de tu empresa.";
    }

    /**
     * Short and explanatory (e.g. "la factura X fue anulada mediante la
     * nota crédito Y"), never a line-by-line dump of every event — that
     * duplicates traceability_summary, which already renders the full,
     * chronologically-ordered list as a card.
     */
    private function traceabilityMessage(array $tool): string
    {
        if (! ($tool['allowed'] ?? true)) {
            return 'Tu rol no tiene permiso para consultar la trazabilidad detallada.';
        }

        $events = $tool['events'] ?? [];

        if (empty($events)) {
            return 'No hay eventos de trazabilidad registrados para ese documento.';
        }

        $invoiceLabel = $tool['invoice_number'] ?? 'Este documento';
        $total = (int) ($tool['total_events'] ?? count($events));
        $last = end($events);
        $noun = $total === 1 ? 'evento' : 'eventos';

        return "{$invoiceLabel} tiene {$total} {$noun} de trazabilidad. El más reciente: {$last['description']}.";
    }

    private function customerMessage(array $tool): string
    {
        $matches = $tool['matches'] ?? [];

        if (empty($matches)) {
            return 'No encontré ese cliente dentro de tu empresa.';
        }

        $total = (int) ($tool['total_matches'] ?? count($matches));

        if ($total === 1) {
            $customer = $matches[0];

            return 'Dato registrado: '.$customer['name'].' · '.$customer['identification'].' · documentos: '.$customer['documents_count'];
        }

        return "Encontré {$total} clientes que coinciden con esa búsqueda.";
    }

    private function productMessage(array $tool): string
    {
        $matches = $tool['matches'] ?? [];

        if (empty($matches)) {
            return 'No encontré ese producto o servicio dentro de tu empresa.';
        }

        $total = (int) ($tool['total_matches'] ?? count($matches));

        if ($total === 1) {
            $product = $matches[0];

            return 'Dato registrado del catálogo: '.$product['sku'].' · '.$product['name'].' · '.$product['status'].' · precio '.ReportFormatter::money($product['price']).' · impuestos '.$product['taxes'];
        }

        return "Encontré {$total} productos o servicios que coinciden con esa búsqueda.";
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

        $customerName = $tool['customer']['name'] ?? 'ese cliente';
        $invoicesTotal = (int) ($tool['invoices_total_matches'] ?? count($tool['invoices'] ?? []));
        $creditNotesTotal = (int) ($tool['credit_notes_total_matches'] ?? count($tool['credit_notes'] ?? []));

        if ($invoicesTotal === 0 && $creditNotesTotal === 0) {
            return "{$customerName} no tiene facturas ni notas crédito registradas todavía.";
        }

        $parts = [];

        if ($invoicesTotal > 0) {
            $parts[] = $invoicesTotal.' '.($invoicesTotal === 1 ? 'factura' : 'facturas');
        }

        if ($creditNotesTotal > 0) {
            $parts[] = $creditNotesTotal.' '.($creditNotesTotal === 1 ? 'nota crédito' : 'notas crédito');
        }

        $summary = 'Encontré '.implode(' y ', $parts).' de '.$customerName.'.';
        $totals = $tool['totals'] ?? null;

        if ($totals && ((float) $totals['billed'] > 0 || (float) $totals['credited'] > 0)) {
            $summary .= ' Neto documental: '.ReportFormatter::money($totals['net']).'.';
        }

        return $summary;
    }

    private function searchCreditNotesMessage(array $tool): string
    {
        $matches = $tool['matches'] ?? [];

        if (empty($matches)) {
            return 'No encontré notas crédito que coincidan con esa búsqueda dentro de tu empresa.';
        }

        $total = (int) ($tool['total_matches'] ?? count($matches));

        if ($total === 1) {
            $creditNote = $matches[0];

            return 'Encontré la nota crédito '.$creditNote['number'].' · factura '.($creditNote['invoice_number'] ?? 'N/A').' · '.$creditNote['status_label'].' · '.ReportFormatter::money($creditNote['total']).'.';
        }

        return "Encontré {$total} notas crédito dentro de tu empresa.";
    }

    private function documentCountsMessage(array $tool): string
    {
        if (! ($tool['allowed'] ?? false)) {
            return 'Tu rol no tiene permiso para consultar estos conteos.';
        }

        $scopeNote = $tool['scope'] === 'own' ? 'tus propios documentos' : 'toda la empresa';

        return "Aquí tienes el resumen de conteos reales del sistema ({$scopeNote}).";
    }

    private function billingSummaryMessage(array $tool): string
    {
        if (! ($tool['allowed'] ?? false)) {
            return 'Tu rol no tiene permiso para consultar el resumen financiero.';
        }

        return 'Resumen financiero de '.$tool['period_label'].' (datos reales de DocumentReportService).';
    }

    private function documentListMessage(array $tool, string $emptyMessage, string $title): string
    {
        if (! ($tool['allowed'] ?? false)) {
            return 'Tu rol no tiene permiso para consultar esta información.';
        }

        $documents = $tool['documents'] ?? [];

        if (empty($documents)) {
            return $emptyMessage;
        }

        $total = (int) ($tool['total_matches'] ?? count($documents));
        $noun = $total === 1 ? 'documento' : 'documentos';

        return "{$title}: {$total} {$noun}.";
    }

    private function rankingMessage(array $tool): string
    {
        if (! ($tool['allowed'] ?? false)) {
            return 'Tu rol no tiene permiso para consultar este ranking.';
        }

        $items = $tool['items'] ?? [];

        if (empty($items)) {
            return 'No encontré suficiente información para calcular ese ranking en '.($tool['period_label'] ?? 'el periodo consultado').'.';
        }

        $isProducts = $tool['type'] === 'products';
        $top = $items[0];
        $label = $isProducts ? ($top['description'] ?? $top['code'] ?? 'Producto') : ($top['customer'] ?? 'Cliente');
        $total = ReportFormatter::money($isProducts ? ($top['invoiced_total'] ?? 0) : ($top['net_total'] ?? $top['invoiced_total'] ?? 0));
        $noun = $isProducts ? 'producto' : 'cliente';

        return "En {$tool['period_label']}, el {$noun} con más facturación es {$label} ({$total}).";
    }
}
