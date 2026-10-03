<?php

namespace Tests\Unit\Invoices;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvalidInvoiceTransitionException;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceStateMachineTest extends TestCase
{
    private function invoiceWithStatus(InvoiceStatus $status): Invoice
    {
        $invoice = new Invoice;
        $invoice->status = $status;

        return $invoice;
    }

    public static function validTransitions(): array
    {
        return [
            'draft -> locally_validated' => [InvoiceStatus::Draft, InvoiceStatus::LocallyValidated],
            'draft -> discarded' => [InvoiceStatus::Draft, InvoiceStatus::Discarded],
            'locally_validated -> draft' => [InvoiceStatus::LocallyValidated, InvoiceStatus::Draft],
            'locally_validated -> sending_simulated' => [InvoiceStatus::LocallyValidated, InvoiceStatus::SendingSimulated],
            'sending_simulated -> issued' => [InvoiceStatus::SendingSimulated, InvoiceStatus::Issued],
            'sending_simulated -> simulated_rejected' => [InvoiceStatus::SendingSimulated, InvoiceStatus::SimulatedRejected],
            'sending_simulated -> technical_error' => [InvoiceStatus::SendingSimulated, InvoiceStatus::TechnicalError],
            'simulated_rejected -> draft' => [InvoiceStatus::SimulatedRejected, InvoiceStatus::Draft],
            'technical_error -> draft' => [InvoiceStatus::TechnicalError, InvoiceStatus::Draft],
            'issued -> voided' => [InvoiceStatus::Issued, InvoiceStatus::Voided],
            'issued -> partially_credited' => [InvoiceStatus::Issued, InvoiceStatus::PartiallyCredited],
            'partially_credited -> voided' => [InvoiceStatus::PartiallyCredited, InvoiceStatus::Voided],
        ];
    }

    #[DataProvider('validTransitions')]
    public function test_valid_transition_is_allowed(InvoiceStatus $from, InvoiceStatus $to): void
    {
        $invoice = $this->invoiceWithStatus($from);

        $this->assertTrue(InvoiceStateMachine::canTransition($from, $to));

        InvoiceStateMachine::transition($invoice, $to);

        $this->assertSame($to, $invoice->status);
    }

    public static function invalidTransitions(): array
    {
        return [
            'draft -> issued (skips the whole pipeline)' => [InvoiceStatus::Draft, InvoiceStatus::Issued],
            'draft -> sending_simulated' => [InvoiceStatus::Draft, InvoiceStatus::SendingSimulated],
            'locally_validated -> issued' => [InvoiceStatus::LocallyValidated, InvoiceStatus::Issued],
            'issued -> draft' => [InvoiceStatus::Issued, InvoiceStatus::Draft],
            'voided -> draft' => [InvoiceStatus::Voided, InvoiceStatus::Draft],
            'discarded -> draft' => [InvoiceStatus::Discarded, InvoiceStatus::Draft],
            'partially_credited -> issued' => [InvoiceStatus::PartiallyCredited, InvoiceStatus::Issued],
            'simulated_rejected -> issued' => [InvoiceStatus::SimulatedRejected, InvoiceStatus::Issued],
        ];
    }

    #[DataProvider('invalidTransitions')]
    public function test_invalid_transition_is_rejected(InvoiceStatus $from, InvoiceStatus $to): void
    {
        $invoice = $this->invoiceWithStatus($from);

        $this->assertFalse(InvoiceStateMachine::canTransition($from, $to));

        $this->expectException(InvalidInvoiceTransitionException::class);
        InvoiceStateMachine::transition($invoice, $to);
    }

    public function test_invalid_transition_does_not_mutate_status(): void
    {
        $invoice = $this->invoiceWithStatus(InvoiceStatus::Draft);

        try {
            InvoiceStateMachine::transition($invoice, InvoiceStatus::Issued);
        } catch (InvalidInvoiceTransitionException) {
            // expected
        }

        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
    }

    public function test_editable_statuses(): void
    {
        $this->assertTrue(InvoiceStateMachine::isEditable(InvoiceStatus::Draft));
        $this->assertTrue(InvoiceStateMachine::isEditable(InvoiceStatus::LocallyValidated));
        $this->assertTrue(InvoiceStateMachine::isEditable(InvoiceStatus::SimulatedRejected));
        $this->assertTrue(InvoiceStateMachine::isEditable(InvoiceStatus::TechnicalError));
    }

    public function test_non_editable_statuses(): void
    {
        $this->assertFalse(InvoiceStateMachine::isEditable(InvoiceStatus::SendingSimulated));
        $this->assertFalse(InvoiceStateMachine::isEditable(InvoiceStatus::Issued));
        $this->assertFalse(InvoiceStateMachine::isEditable(InvoiceStatus::Voided));
        $this->assertFalse(InvoiceStateMachine::isEditable(InvoiceStatus::Discarded));
        $this->assertFalse(InvoiceStateMachine::isEditable(InvoiceStatus::PartiallyCredited));
    }

    public function test_terminal_statuses(): void
    {
        $this->assertTrue(InvoiceStateMachine::isTerminal(InvoiceStatus::Voided));
        $this->assertTrue(InvoiceStateMachine::isTerminal(InvoiceStatus::Discarded));
        $this->assertFalse(InvoiceStateMachine::isTerminal(InvoiceStatus::PartiallyCredited));
    }

    public function test_non_terminal_statuses(): void
    {
        $this->assertFalse(InvoiceStateMachine::isTerminal(InvoiceStatus::Draft));
        $this->assertFalse(InvoiceStateMachine::isTerminal(InvoiceStatus::LocallyValidated));
        $this->assertFalse(InvoiceStateMachine::isTerminal(InvoiceStatus::SendingSimulated));
        // issued still has voided/partially_credited as valid future destinations.
        $this->assertFalse(InvoiceStateMachine::isTerminal(InvoiceStatus::Issued));
        // partially credited invoices can still be voided by a later credit note for the remaining balance.
        $this->assertFalse(InvoiceStateMachine::isTerminal(InvoiceStatus::PartiallyCredited));
    }
}
