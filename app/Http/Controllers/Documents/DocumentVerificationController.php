<?php

namespace App\Http\Controllers\Documents;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceTraceLogger;
use Illuminate\View\View;

class DocumentVerificationController extends Controller
{
    public function __construct(private readonly InvoiceTraceLogger $logger) {}

    public function show(string $token): View
    {
        $invoice = Invoice::query()
            ->withoutGlobalScopes()
            ->where('verification_token', $token)
            ->where('status', InvoiceStatus::Issued->value)
            ->firstOrFail();

        $this->logger->log(
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
}
