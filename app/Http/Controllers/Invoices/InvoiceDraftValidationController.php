<?php

namespace App\Http\Controllers\Invoices;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceHasBlockingIssuesException;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceDraftContextBuilder;
use App\Services\Invoices\InvoiceStateMachine;
use App\Services\Invoices\InvoiceTraceLogger;
use App\Services\Invoices\IssueInvoiceService;
use App\Services\Invoices\Validation\ValidationEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InvoiceDraftValidationController extends Controller
{
    public function __construct(
        private readonly InvoiceDraftContextBuilder $contextBuilder,
        private readonly ValidationEngine $engine,
        private readonly InvoiceTraceLogger $logger,
        private readonly IssueInvoiceService $issueService,
    ) {}

    /** Read-only: never mutates state, just shows where the draft stands. */
    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $context = $this->contextBuilder->buildFromInvoice($invoice);
        $results = $this->engine->run($context);

        return view('invoices.drafts.validation', [
            'invoice' => $invoice,
            'calculation' => $context->calculation,
            'validation' => $results,
        ]);
    }

    public function validate(Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        if (! in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::LocallyValidated], true)) {
            abort(403, 'Esta factura no se puede validar en su estado actual.');
        }

        $context = $this->contextBuilder->buildFromInvoice($invoice);
        $results = $this->engine->run($context);

        if ($results->hasBlocking()) {
            if ($invoice->status === InvoiceStatus::LocallyValidated) {
                InvoiceStateMachine::transition($invoice, InvoiceStatus::Draft);
                $invoice->save();
            }

            $this->logger->log(
                $invoice,
                'local_validation_failed',
                'La validación local encontró bloqueos.',
                InvoiceStatus::Draft,
                InvoiceStatus::Draft,
                ['blocking_count' => $results->blockingCount(), 'warning_count' => $results->warningCount()],
            );

            return redirect()->route('invoices.draft.validation', $invoice)
                ->with('error', 'Hay bloqueos que debes corregir antes de continuar.');
        }

        if ($invoice->status === InvoiceStatus::Draft) {
            InvoiceStateMachine::transition($invoice, InvoiceStatus::LocallyValidated);
            $invoice->save();
            $this->logger->log(
                $invoice,
                'local_validation_passed',
                'La factura pasó la validación local.',
                InvoiceStatus::Draft,
                InvoiceStatus::LocallyValidated,
                ['warning_count' => $results->warningCount()],
            );
        }

        return redirect()->route('invoices.draft.validation', $invoice)
            ->with('status', 'Validación superada. Ya puedes emitir la factura.');
    }

    public function issue(Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        if ($invoice->status !== InvoiceStatus::LocallyValidated) {
            return redirect()->route('invoices.draft.validation', $invoice)
                ->with('error', 'Debes validar la factura antes de emitirla.');
        }

        try {
            $invoice = $this->issueService->issue($invoice);
        } catch (InvoiceHasBlockingIssuesException $exception) {
            return redirect()->route('invoices.draft.validation', $invoice)->with('error', $exception->getMessage());
        }

        return match ($invoice->status) {
            InvoiceStatus::Issued => redirect()->route('invoices.show', $invoice)
                ->with('status', "Factura {$invoice->number} emitida correctamente (simulado)."),
            InvoiceStatus::SimulatedRejected => redirect()->route('invoices.draft.validation', $invoice)
                ->with('error', 'La validación simulada ante la DIAN fue rechazada: '.$invoice->dian_simulation_message),
            InvoiceStatus::TechnicalError => redirect()->route('invoices.draft.validation', $invoice)
                ->with('error', 'Error técnico simulado: '.$invoice->dian_simulation_message),
            default => redirect()->route('invoices.draft.validation', $invoice),
        };
    }
}
