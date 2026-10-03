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

        if ($tool = $this->firstTool($toolResults, 'find_invoice')) {
            return new AssistantResponse($prefix."\n\n".$this->findInvoiceMessage($tool));
        }

        if ($tool = $this->firstTool($toolResults, 'find_customer')) {
            return new AssistantResponse($prefix."\n\n".$this->customerMessage($tool));
        }

        if ($tool = $this->firstTool($toolResults, 'find_product')) {
            return new AssistantResponse($prefix."\n\n".$this->productMessage($tool));
        }

        if ($tool = $this->firstTool($toolResults, 'credit_note_details')) {
            return new AssistantResponse($prefix."\n\n".$this->creditNoteMessage($tool));
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

        return "Encontré estas facturas dentro de tu empresa:\n".$matches;
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
}
