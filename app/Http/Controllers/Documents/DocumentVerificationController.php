<?php

namespace App\Http\Controllers\Documents;

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\CreditNotes\CreditNoteTraceLogger;
use App\Services\Invoices\InvoiceTraceLogger;
use Illuminate\View\View;

/**
 * Public, unauthenticated verification page a QR code on either
 * document type links to. Both invoices and credit notes draw their
 * verification_token from the same random, per-table-unique keyspace
 * (see CreditNotePdfService::ensureVerificationToken), so looking the
 * token up in one table and then the other is safe and unambiguous.
 */
class DocumentVerificationController extends Controller
{
    public function __construct(
        private readonly InvoiceTraceLogger $invoiceLogger,
        private readonly CreditNoteTraceLogger $creditNoteLogger,
    ) {}

    public function show(string $token): View
    {
        $invoice = Invoice::query()
            ->withoutGlobalScopes()
            ->where('verification_token', $token)
            ->where('status', InvoiceStatus::Issued->value)
            ->first();

        if ($invoice !== null) {
            $this->invoiceLogger->log(
                $invoice,
                'verification_page_viewed',
                'Se consultó la página pública de verificación del documento de prueba.',
                $invoice->status,
                $invoice->status,
                ['source' => 'public_verification'],
                useAuthenticatedUser: false,
            );

            return view('documents.verify', [
                'invoice' => $invoice,
                'issuer' => $invoice->issuer_snapshot ?? [],
            ]);
        }

        $creditNote = CreditNote::query()
            ->withoutGlobalScopes()
            ->where('verification_token', $token)
            ->where('status', CreditNoteStatus::Issued->value)
            ->with('invoice')
            ->firstOrFail();

        $this->creditNoteLogger->log(
            $creditNote,
            'credit_note_verification_page_viewed',
            'Se consultó la página pública de verificación de la nota crédito de prueba.',
            $creditNote->status,
            $creditNote->status,
            ['source' => 'public_verification'],
        );

        return view('documents.verify-credit-note', [
            'creditNote' => $creditNote,
            'issuer' => $creditNote->invoice->issuer_snapshot ?? [],
        ]);
    }
}
