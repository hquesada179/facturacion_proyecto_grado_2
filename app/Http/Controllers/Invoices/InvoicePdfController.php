<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoices\InvoicePdfService;
use App\Services\Invoices\InvoiceTraceLogger;
use App\Services\Invoices\SimulatedInvoiceDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class InvoicePdfController extends Controller
{
    public function __construct(
        private readonly InvoicePdfService $pdfService,
        private readonly InvoiceTraceLogger $logger,
        private readonly SimulatedInvoiceDeliveryService $deliveryService,
    ) {}

    public function show(Invoice $invoice): Response
    {
        $this->authorize('downloadPdf', $invoice);

        try {
            $invoice = $this->pdfService->ensureGenerated($invoice);
        } catch (RuntimeException $exception) {
            abort(409, $exception->getMessage());
        }

        return response(Storage::disk('local')->get($invoice->pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->downloadName($invoice).'"',
        ]);
    }

    public function download(Invoice $invoice): Response
    {
        $this->authorize('downloadPdf', $invoice);

        try {
            $invoice = $this->pdfService->ensureGenerated($invoice);
        } catch (RuntimeException $exception) {
            abort(409, $exception->getMessage());
        }

        $this->logger->log(
            $invoice,
            'pdf_downloaded',
            'Se descargó la representación gráfica PDF de la factura de prueba.',
            $invoice->status,
            $invoice->status,
            ['path' => $invoice->pdf_path, 'hash' => $invoice->pdf_hash],
        );

        return response(Storage::disk('local')->get($invoice->pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->downloadName($invoice).'"',
        ]);
    }

    public function send(Invoice $invoice): RedirectResponse
    {
        $this->authorize('sendSimulatedDelivery', $invoice);

        $invoice = $this->deliveryService->send($invoice);

        if ($invoice->delivery_status === 'failed_simulated') {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', 'No se pudo simular el envío: '.$invoice->delivery_error);
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('status', 'Entrega simulada registrada en el mailer log.');
    }

    private function downloadName(Invoice $invoice): string
    {
        $number = Str::of($invoice->number ?? 'factura-'.$invoice->id)
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
            ->trim('-')
            ->lower();

        return "factura-prueba-{$number}.pdf";
    }
}
