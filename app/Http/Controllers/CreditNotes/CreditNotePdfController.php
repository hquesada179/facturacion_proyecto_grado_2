<?php

namespace App\Http\Controllers\CreditNotes;

use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use App\Services\CreditNotes\CreditNotePdfService;
use App\Services\CreditNotes\CreditNoteTraceLogger;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CreditNotePdfController extends Controller
{
    public function __construct(
        private readonly CreditNotePdfService $pdfService,
        private readonly CreditNoteTraceLogger $logger,
    ) {}

    public function show(CreditNote $creditNote): Response
    {
        $this->authorize('downloadPdf', $creditNote);

        try {
            $creditNote = $this->pdfService->ensureGenerated($creditNote);
        } catch (RuntimeException $exception) {
            abort(409, $exception->getMessage());
        }

        return response(Storage::disk('local')->get($creditNote->pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->downloadName($creditNote).'"',
        ]);
    }

    public function download(CreditNote $creditNote): Response
    {
        $this->authorize('downloadPdf', $creditNote);

        try {
            $creditNote = $this->pdfService->ensureGenerated($creditNote);
        } catch (RuntimeException $exception) {
            abort(409, $exception->getMessage());
        }

        $this->logger->log(
            $creditNote,
            'credit_note_pdf_downloaded',
            'Se descargó la representación gráfica PDF de la nota crédito de prueba.',
            $creditNote->status,
            $creditNote->status,
            ['path' => $creditNote->pdf_path, 'hash' => $creditNote->pdf_hash],
        );

        return response(Storage::disk('local')->get($creditNote->pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->downloadName($creditNote).'"',
        ]);
    }

    private function downloadName(CreditNote $creditNote): string
    {
        $number = Str::of($creditNote->number ?? 'nota-credito-'.$creditNote->id)
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
            ->trim('-')
            ->lower();

        return "nota-credito-prueba-{$number}.pdf";
    }
}
