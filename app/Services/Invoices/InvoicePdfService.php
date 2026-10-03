<?php

namespace App\Services\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class InvoicePdfService
{
    public function __construct(
        private readonly InvoiceTraceLogger $logger,
        private readonly InvoiceQrCodeService $qrCode,
    ) {}

    public function ensureGenerated(Invoice $invoice): Invoice
    {
        $this->ensureIssued($invoice);
        $invoice = $this->ensureVerificationToken($invoice);

        if ($invoice->pdf_path !== null) {
            if (Storage::disk('local')->exists($invoice->pdf_path)) {
                return $invoice;
            }

            throw new RuntimeException('La factura ya tiene un PDF histórico registrado, pero el archivo no está disponible.');
        }

        $contents = Pdf::loadView('invoices.pdf', $this->viewData($invoice))
            ->setPaper('letter')
            ->output();

        $path = $this->pathFor($invoice);
        Storage::disk('local')->put($path, $contents);
        $hash = hash('sha256', $contents);

        $invoice->forceFill([
            'pdf_path' => $path,
            'pdf_hash' => $hash,
            'pdf_generated_at' => now(),
        ])->save();

        $this->logger->log(
            $invoice,
            'pdf_generated',
            'Se generó la representación gráfica PDF de la factura de prueba.',
            $invoice->status,
            $invoice->status,
            ['path' => $path, 'hash' => $hash],
        );

        return $invoice->refresh();
    }

    public function viewData(Invoice $invoice): array
    {
        $this->ensureIssued($invoice);
        $invoice = $this->ensureVerificationToken($invoice);
        $invoice->loadMissing(['company', 'items.itemTaxes', 'numberingResolution']);

        $taxesByCode = [];
        foreach ($invoice->items as $item) {
            foreach ($item->itemTaxes as $itemTax) {
                $taxesByCode[$itemTax->code] ??= [
                    'code' => $itemTax->code,
                    'name' => $itemTax->name,
                    'rate' => (string) $itemTax->rate,
                    'base' => BigDecimal::zero(),
                    'value' => BigDecimal::zero(),
                ];

                $taxesByCode[$itemTax->code]['base'] = $taxesByCode[$itemTax->code]['base']->plus($itemTax->base);
                $taxesByCode[$itemTax->code]['value'] = $taxesByCode[$itemTax->code]['value']->plus($itemTax->value);
            }
        }

        foreach ($taxesByCode as $code => $tax) {
            $taxesByCode[$code]['base'] = (string) $tax['base']->toScale(2, RoundingMode::HalfEven);
            $taxesByCode[$code]['value'] = (string) $tax['value']->toScale(2, RoundingMode::HalfEven);
        }

        $totalDiscounts = $invoice->items->reduce(
            fn (BigDecimal $carry, $item): BigDecimal => $carry->plus($item->discount_total),
            BigDecimal::zero()
        )->toScale(2, RoundingMode::HalfEven);

        $taxableBase = $invoice->items->reduce(
            fn (BigDecimal $carry, $item): BigDecimal => $carry->plus($item->taxable_base),
            BigDecimal::zero()
        )->toScale(2, RoundingMode::HalfEven);

        $verificationUrl = $this->verificationUrl($invoice);

        return [
            'invoice' => $invoice,
            'issuer' => $invoice->issuer_snapshot ?? [],
            'customer' => $invoice->customer_snapshot ?? [],
            'taxesByCode' => $taxesByCode,
            'totalDiscounts' => (string) $totalDiscounts,
            'taxableBase' => (string) $taxableBase,
            'verificationUrl' => $verificationUrl,
            'qrDataUri' => $this->qrCode->dataUri($verificationUrl),
        ];
    }

    public function verificationUrl(Invoice $invoice): string
    {
        $invoice = $this->ensureVerificationToken($invoice);

        return route('documents.verify', ['token' => $invoice->verification_token]);
    }

    public function ensureVerificationToken(Invoice $invoice): Invoice
    {
        if ($invoice->verification_token !== null) {
            return $invoice;
        }

        do {
            $token = Str::random(64);
        } while (Invoice::withoutGlobalScopes()->where('verification_token', $token)->exists());

        $invoice->forceFill(['verification_token' => $token])->save();

        return $invoice->refresh();
    }

    private function ensureIssued(Invoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatus::Issued) {
            throw new RuntimeException('Solo una factura emitida puede generar PDF definitivo.');
        }
    }

    private function pathFor(Invoice $invoice): string
    {
        $number = Str::of($invoice->number ?? 'factura-'.$invoice->id)
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
            ->trim('-')
            ->lower();

        return "invoices/{$invoice->company_id}/{$number}-{$invoice->id}.pdf";
    }
}
