<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class SimulatedInvoiceDelivery extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Factura de prueba '.$this->invoice->number.' - entrega simulada',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoices.simulated-delivery',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->invoice->pdf_path || ! Storage::disk('local')->exists($this->invoice->pdf_path)) {
            return [];
        }

        return [
            Attachment::fromPath(Storage::disk('local')->path($this->invoice->pdf_path))
                ->as('factura-prueba-'.$this->invoice->number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
