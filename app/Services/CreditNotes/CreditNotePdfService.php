<?php

namespace App\Services\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use Barryvdh\DomPDF\Facade\Pdf;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CreditNotePdfService
{
    public function __construct(private readonly CreditNoteTraceLogger $logger) {}

    public function ensureGenerated(CreditNote $creditNote): CreditNote
    {
        $this->ensureIssued($creditNote);

        if ($creditNote->pdf_path !== null) {
            if (Storage::disk('local')->exists($creditNote->pdf_path)) {
                return $creditNote;
            }

            throw new RuntimeException('La nota crédito ya tiene un PDF histórico registrado, pero el archivo no está disponible.');
        }

        $contents = Pdf::loadView('credit-notes.pdf', $this->viewData($creditNote))
            ->setPaper('letter')
            ->output();

        $path = $this->pathFor($creditNote);
        Storage::disk('local')->put($path, $contents);
        $hash = hash('sha256', $contents);

        $creditNote->forceFill([
            'pdf_path' => $path,
            'pdf_hash' => $hash,
            'pdf_generated_at' => now(),
        ])->save();

        $this->logger->log(
            $creditNote,
            'credit_note_pdf_generated',
            'Se generó la representación gráfica PDF de la nota crédito de prueba.',
            $creditNote->status,
            $creditNote->status,
            ['path' => $path, 'hash' => $hash],
        );

        return $creditNote->refresh();
    }

    public function viewData(CreditNote $creditNote): array
    {
        $this->ensureIssued($creditNote);
        $creditNote->loadMissing(['invoice.items', 'invoice.company', 'items']);

        $taxesByCode = [];
        foreach ($creditNote->items as $item) {
            foreach ($item->tax_snapshot ?? [] as $tax) {
                $code = $tax['code'] ?? 'TAX';
                $taxesByCode[$code] ??= [
                    'code' => $code,
                    'name' => $tax['name'] ?? 'Impuesto',
                    'rate' => $tax['rate'] ?? '0',
                    'base' => BigDecimal::zero(),
                    'value' => BigDecimal::zero(),
                ];

                $taxesByCode[$code]['base'] = $taxesByCode[$code]['base']->plus($tax['base'] ?? 0);
                $taxesByCode[$code]['value'] = $taxesByCode[$code]['value']->plus($tax['value'] ?? 0);
            }
        }

        foreach ($taxesByCode as $code => $tax) {
            $taxesByCode[$code]['base'] = (string) $tax['base']->toScale(2, RoundingMode::HalfEven);
            $taxesByCode[$code]['value'] = (string) $tax['value']->toScale(2, RoundingMode::HalfEven);
        }

        return [
            'creditNote' => $creditNote,
            'invoice' => $creditNote->invoice,
            'issuer' => $creditNote->invoice->issuer_snapshot ?? [],
            'customer' => $creditNote->invoice->customer_snapshot ?? [],
            'taxesByCode' => $taxesByCode,
        ];
    }

    private function ensureIssued(CreditNote $creditNote): void
    {
        if ($creditNote->status !== CreditNoteStatus::Issued) {
            throw new RuntimeException('Solo una nota crédito emitida puede generar PDF definitivo.');
        }
    }

    private function pathFor(CreditNote $creditNote): string
    {
        $number = Str::of($creditNote->number ?? 'nota-credito-'.$creditNote->id)
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
            ->trim('-')
            ->lower();

        return "credit-notes/{$creditNote->company_id}/{$number}-{$creditNote->id}.pdf";
    }
}
