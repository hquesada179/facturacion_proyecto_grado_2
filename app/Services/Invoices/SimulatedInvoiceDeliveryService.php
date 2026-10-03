<?php

namespace App\Services\Invoices;

use App\Enums\InvoiceStatus;
use App\Mail\SimulatedInvoiceDelivery;
use App\Models\Invoice;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SimulatedInvoiceDeliveryService
{
    public function __construct(
        private readonly InvoicePdfService $pdfService,
        private readonly InvoiceTraceLogger $logger,
    ) {}

    public function send(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Issued) {
            abort(403, 'Solo una factura emitida puede enviarse de forma simulada.');
        }

        $invoice->loadMissing('customer');
        $recipient = $invoice->customer_snapshot['email'] ?? $invoice->customer?->email;

        $invoice->forceFill([
            'delivery_status' => 'pending_simulated',
            'delivery_error' => null,
        ])->save();

        $this->logger->log(
            $invoice,
            'delivery_simulated',
            'Entrega simulada pendiente.',
            $invoice->status,
            $invoice->status,
            ['status' => 'pending_simulated', 'mailer' => 'log'],
        );

        if (! $recipient) {
            return $this->fail($invoice, 'El cliente histórico no tiene correo registrado.');
        }

        try {
            $invoice = $this->pdfService->ensureGenerated($invoice);
            Mail::mailer('log')->to($recipient)->send(new SimulatedInvoiceDelivery($invoice));
        } catch (Throwable $throwable) {
            return $this->fail($invoice, $throwable->getMessage());
        }

        $invoice->forceFill([
            'delivery_status' => 'sent_simulated',
            'delivery_simulated_at' => now(),
            'delivery_error' => null,
        ])->save();

        $this->logger->log(
            $invoice,
            'delivery_simulated',
            'Factura enviada mediante entrega simulada por correo log.',
            $invoice->status,
            $invoice->status,
            ['status' => 'sent_simulated', 'recipient' => $recipient, 'mailer' => 'log'],
        );

        return $invoice->refresh();
    }

    private function fail(Invoice $invoice, string $reason): Invoice
    {
        $invoice->forceFill([
            'delivery_status' => 'failed_simulated',
            'delivery_simulated_at' => now(),
            'delivery_error' => $reason,
        ])->save();

        $this->logger->log(
            $invoice,
            'delivery_simulated',
            'Falló la entrega simulada de la factura.',
            $invoice->status,
            $invoice->status,
            ['status' => 'failed_simulated', 'reason' => $reason, 'mailer' => 'log'],
        );

        return $invoice->refresh();
    }
}
