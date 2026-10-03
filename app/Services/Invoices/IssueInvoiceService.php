<?php

namespace App\Services\Invoices;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceHasBlockingIssuesException;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Invoices\Validation\ValidationEngine;
use App\Services\Numbering\NumberingService;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates emission end to end: re-validates the real draft,
 * transitions through sending_simulated, calls DianSimulator, and only on
 * an approved simulation reserves a definitive number (NumberingService,
 * Fase 3 — atomic, never reused), snapshots company/customer, and
 * generates the simulated CUFE. A rejected or erroring simulation never
 * touches numbering at all. Everything happens inside one transaction and
 * every step is recorded via InvoiceTraceLogger.
 */
class IssueInvoiceService
{
    public function __construct(
        private readonly InvoiceDraftContextBuilder $contextBuilder,
        private readonly ValidationEngine $engine,
        private readonly NumberingService $numberingService,
        private readonly DianSimulator $dianSimulator,
        private readonly CufeGenerator $cufeGenerator,
        private readonly InvoiceTraceLogger $logger,
    ) {}

    /**
     * @param  'validada'|'rechazada'|'error'|null  $forceScenario  Forwarded
     *                                                              to DianSimulator — the real controller never passes this (the
     *                                                              service already gates on zero blocking issues before ever
     *                                                              calling the simulator, so automatic mode always approves here).
     *                                                              Tests use it to deterministically exercise the rejected/error
     *                                                              branches; it's also where a future "simulate this scenario"
     *                                                              tool would hook in.
     */
    public function issue(Invoice $invoice, ?string $forceScenario = null): Invoice
    {
        $context = $this->contextBuilder->buildFromInvoice($invoice);
        $results = $this->engine->run($context);

        if ($results->hasBlocking()) {
            $this->logger->log(
                $invoice,
                'local_validation_failed',
                'La factura ya no pasa la validación local y no puede emitirse.',
                InvoiceStatus::LocallyValidated,
                InvoiceStatus::LocallyValidated,
                ['results' => $results->toArray()],
            );

            throw new InvoiceHasBlockingIssuesException('La factura tiene bloqueos y no puede emitirse.');
        }

        return DB::transaction(function () use ($invoice, $context, $forceScenario): Invoice {
            $from = $invoice->status;
            $this->logger->log($invoice, 'issuance_requested', 'Se solicitó la emisión de la factura.', $from, $from);

            InvoiceStateMachine::transition($invoice, InvoiceStatus::SendingSimulated);
            $invoice->save();
            $this->logger->log($invoice, 'simulated_validation_started', 'Se inició la validación simulada ante la DIAN.', $from, InvoiceStatus::SendingSimulated);

            $simulation = $this->dianSimulator->simulate($invoice, $context, $forceScenario);
            $invoice->dian_simulation_result = $simulation->status;
            $invoice->dian_simulation_message = $simulation->message;
            $invoice->validation_at = now();

            if ($simulation->status === 'rechazada') {
                InvoiceStateMachine::transition($invoice, InvoiceStatus::SimulatedRejected);
                $invoice->save();
                $this->logger->log($invoice, 'simulated_validation_rejected', $simulation->message, InvoiceStatus::SendingSimulated, InvoiceStatus::SimulatedRejected);

                return $invoice;
            }

            if ($simulation->status === 'error') {
                InvoiceStateMachine::transition($invoice, InvoiceStatus::TechnicalError);
                $invoice->save();
                $this->logger->log($invoice, 'technical_error', $simulation->message, InvoiceStatus::SendingSimulated, InvoiceStatus::TechnicalError);

                return $invoice;
            }

            $this->logger->log($invoice, 'simulated_validation_approved', $simulation->message, InvoiceStatus::SendingSimulated, InvoiceStatus::SendingSimulated);

            return $this->finishIssuing($invoice);
        });
    }

    private function finishIssuing(Invoice $invoice): Invoice
    {
        $resolution = $this->numberingService->activeResolutionFor($invoice->company, DocumentType::Invoice);

        if ($resolution === null) {
            return $this->failTechnically($invoice, 'No hay una resolución de numeración vigente para facturas.', ['reason' => 'no_active_resolution']);
        }

        try {
            $consecutive = $this->numberingService->reserveNextNumber($resolution);
        } catch (Exception $exception) {
            return $this->failTechnically($invoice, $exception->getMessage(), ['reason' => 'numbering_exhausted']);
        }

        $invoice->number = sprintf('%s-%06d', $resolution->prefix, $consecutive);
        $invoice->numbering_resolution_id = $resolution->id;
        $invoice->issued_at = now();
        $invoice->issuer_snapshot = $this->snapshotCompany($invoice->company);
        $invoice->customer_snapshot = $this->snapshotCustomer($invoice->customer);
        InvoiceStateMachine::transition($invoice, InvoiceStatus::Issued);
        $invoice->simulated_cufe = $this->cufeGenerator->generate($invoice);
        $invoice->save();

        $this->logger->log(
            $invoice,
            'issued',
            "Factura emitida con número {$invoice->number}.",
            InvoiceStatus::SendingSimulated,
            InvoiceStatus::Issued,
            ['number' => $invoice->number, 'cufe' => $invoice->simulated_cufe],
        );

        return $invoice;
    }

    private function failTechnically(Invoice $invoice, string $message, array $metadata): Invoice
    {
        InvoiceStateMachine::transition($invoice, InvoiceStatus::TechnicalError);
        $invoice->dian_simulation_result = 'error';
        $invoice->dian_simulation_message = $message;
        $invoice->save();
        $this->logger->log($invoice, 'technical_error', $message, InvoiceStatus::SendingSimulated, InvoiceStatus::TechnicalError, $metadata);

        return $invoice;
    }

    private function snapshotCompany(Company $company): array
    {
        return [
            'name' => $company->name,
            'legal_name' => $company->legal_name,
            'nit' => $company->nit,
            'nit_dv' => $company->nit_dv,
            'address' => $company->address,
            'city' => $company->city,
            'department' => $company->department,
            'country' => $company->country,
            'email' => $company->email,
            'phone' => $company->phone,
        ];
    }

    private function snapshotCustomer(Customer $customer): array
    {
        return [
            'name' => $customer->name,
            'identification_type' => $customer->identification_type,
            'identification_number' => $customer->identification_number,
            'dv' => $customer->dv,
            'email' => $customer->email,
            'address' => $customer->address,
            'city' => $customer->city,
        ];
    }
}
