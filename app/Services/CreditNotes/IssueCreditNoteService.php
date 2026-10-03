<?php

namespace App\Services\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Models\CreditNote;
use App\Services\Invoices\InvoiceStateMachine;
use App\Services\Invoices\InvoiceTraceLogger;
use App\Services\Numbering\NumberingService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueCreditNoteService
{
    public function __construct(
        private readonly CreditNoteDraftService $draftService,
        private readonly CreditNoteValidationService $validator,
        private readonly NumberingService $numberingService,
        private readonly CreditNoteDianSimulator $dianSimulator,
        private readonly CreditNoteCufeGenerator $cufeGenerator,
        private readonly CreditNoteTraceLogger $creditNoteLogger,
        private readonly InvoiceTraceLogger $invoiceLogger,
    ) {}

    public function issue(CreditNote $creditNote, ?string $forceScenario = null): CreditNote
    {
        if ($creditNote->status !== CreditNoteStatus::LocallyValidated) {
            throw ValidationException::withMessages([
                'status' => 'Debes validar la nota crédito antes de emitirla.',
            ]);
        }

        return DB::transaction(function () use ($creditNote, $forceScenario): CreditNote {
            $creditNote->loadMissing(['invoice.company', 'items.invoiceItem']);

            $this->creditNoteLogger->log(
                $creditNote,
                'credit_note_issuance_requested',
                'Se solicitó la emisión simulada de la nota crédito.',
                $creditNote->status,
                $creditNote->status,
            );

            CreditNoteStateMachine::transition($creditNote, CreditNoteStatus::SendingSimulated);
            $creditNote->save();

            $simulation = $this->dianSimulator->simulate($creditNote, $forceScenario);
            $creditNote->dian_simulation_result = $simulation->status;
            $creditNote->dian_simulation_message = $simulation->message;
            $creditNote->validation_at = now();

            if ($simulation->status === 'rechazada') {
                CreditNoteStateMachine::transition($creditNote, CreditNoteStatus::SimulatedRejected);
                $creditNote->save();
                $this->creditNoteLogger->log(
                    $creditNote,
                    'credit_note_simulated_rejected',
                    $simulation->message,
                    CreditNoteStatus::SendingSimulated,
                    CreditNoteStatus::SimulatedRejected,
                );

                return $creditNote->refresh();
            }

            if ($simulation->status === 'error') {
                CreditNoteStateMachine::transition($creditNote, CreditNoteStatus::TechnicalError);
                $creditNote->save();
                $this->creditNoteLogger->log(
                    $creditNote,
                    'credit_note_technical_error',
                    $simulation->message,
                    CreditNoteStatus::SendingSimulated,
                    CreditNoteStatus::TechnicalError,
                );

                return $creditNote->refresh();
            }

            $this->creditNoteLogger->log(
                $creditNote,
                'credit_note_simulated_approved',
                $simulation->message,
                CreditNoteStatus::SendingSimulated,
                CreditNoteStatus::SendingSimulated,
            );

            return $this->finishIssuing($creditNote);
        });
    }

    public function validate(CreditNote $creditNote): CreditNote
    {
        return $this->draftService->validate($creditNote);
    }

    private function finishIssuing(CreditNote $creditNote): CreditNote
    {
        $resolution = $this->numberingService->activeResolutionFor($creditNote->company, DocumentType::CreditNote);

        if ($resolution === null) {
            return $this->failTechnically($creditNote, 'No hay una resolución de numeración vigente para notas crédito.', ['reason' => 'no_active_resolution']);
        }

        try {
            $consecutive = $this->numberingService->reserveNextNumber($resolution);
        } catch (Exception $exception) {
            return $this->failTechnically($creditNote, $exception->getMessage(), ['reason' => 'numbering_exhausted']);
        }

        $creditNote->prefix = $resolution->prefix;
        $creditNote->number = sprintf('%s-%06d', $resolution->prefix, $consecutive);
        $creditNote->issued_at = now();
        $creditNote->simulated_dian_code = 'SIM-NC-'.$creditNote->id;
        CreditNoteStateMachine::transition($creditNote, CreditNoteStatus::Issued);
        $creditNote->simulated_cufe = $this->cufeGenerator->generate($creditNote);
        $creditNote->save();

        $this->creditNoteLogger->log(
            $creditNote,
            'credit_note_issued',
            "Nota crédito {$creditNote->number} emitida en simulación.",
            CreditNoteStatus::SendingSimulated,
            CreditNoteStatus::Issued,
            ['number' => $creditNote->number, 'simulated_cufe' => $creditNote->simulated_cufe],
        );

        $this->applyInvoiceEffect($creditNote);

        return $creditNote->refresh();
    }

    private function failTechnically(CreditNote $creditNote, string $message, array $metadata): CreditNote
    {
        CreditNoteStateMachine::transition($creditNote, CreditNoteStatus::TechnicalError);
        $creditNote->dian_simulation_result = 'error';
        $creditNote->dian_simulation_message = $message;
        $creditNote->save();
        $this->creditNoteLogger->log(
            $creditNote,
            'credit_note_technical_error',
            $message,
            CreditNoteStatus::SendingSimulated,
            CreditNoteStatus::TechnicalError,
            $metadata,
        );

        return $creditNote->refresh();
    }

    private function applyInvoiceEffect(CreditNote $creditNote): void
    {
        $invoice = $creditNote->invoice()->with('items')->first();
        $from = $invoice->status;

        if ($this->validator->invoiceIsFullyCredited($invoice)) {
            if (InvoiceStateMachine::canTransition($invoice->status, InvoiceStatus::Voided)) {
                InvoiceStateMachine::transition($invoice, InvoiceStatus::Voided);
                $invoice->save();
                $this->invoiceLogger->log(
                    $invoice,
                    'invoice_voided',
                    'La factura quedó anulada por nota crédito total simulada.',
                    $from,
                    InvoiceStatus::Voided,
                    ['credit_note_id' => $creditNote->id, 'credit_note_number' => $creditNote->number],
                );
            }

            return;
        }

        if ($invoice->status === InvoiceStatus::Issued) {
            InvoiceStateMachine::transition($invoice, InvoiceStatus::PartiallyCredited);
            $invoice->save();
            $this->invoiceLogger->log(
                $invoice,
                'invoice_partially_credited',
                'La factura quedó parcialmente acreditada por nota crédito simulada.',
                $from,
                InvoiceStatus::PartiallyCredited,
                ['credit_note_id' => $creditNote->id, 'credit_note_number' => $creditNote->number],
            );
        }
    }
}
