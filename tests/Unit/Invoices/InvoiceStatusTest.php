<?php

namespace Tests\Unit\Invoices;

use App\Enums\InvoiceStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Fase 6 fix: wasIssued() and canReceiveCreditNote() are the single
 * source of truth for "this invoice has a definitive PDF/trace worth
 * showing" and "a credit note can still target this invoice" —
 * replacing literal [Issued, PartiallyCredited] / [..., Voided] arrays
 * that used to be duplicated across policies, services and the
 * controller that lists eligible invoices.
 */
class InvoiceStatusTest extends TestCase
{
    public static function wasIssuedCases(): array
    {
        return [
            'draft' => [InvoiceStatus::Draft, false],
            'locally_validated' => [InvoiceStatus::LocallyValidated, false],
            'sending_simulated' => [InvoiceStatus::SendingSimulated, false],
            'simulated_rejected' => [InvoiceStatus::SimulatedRejected, false],
            'technical_error' => [InvoiceStatus::TechnicalError, false],
            'discarded' => [InvoiceStatus::Discarded, false],
            'issued' => [InvoiceStatus::Issued, true],
            'partially_credited' => [InvoiceStatus::PartiallyCredited, true],
            'voided' => [InvoiceStatus::Voided, true],
        ];
    }

    #[DataProvider('wasIssuedCases')]
    public function test_was_issued(InvoiceStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->wasIssued());
    }

    public static function canReceiveCreditNoteCases(): array
    {
        return [
            'draft' => [InvoiceStatus::Draft, false],
            'locally_validated' => [InvoiceStatus::LocallyValidated, false],
            'sending_simulated' => [InvoiceStatus::SendingSimulated, false],
            'simulated_rejected' => [InvoiceStatus::SimulatedRejected, false],
            'technical_error' => [InvoiceStatus::TechnicalError, false],
            'discarded' => [InvoiceStatus::Discarded, false],
            'issued' => [InvoiceStatus::Issued, true],
            'partially_credited' => [InvoiceStatus::PartiallyCredited, true],
            // Fully credited: nothing left to credit.
            'voided' => [InvoiceStatus::Voided, false],
        ];
    }

    #[DataProvider('canReceiveCreditNoteCases')]
    public function test_can_receive_credit_note(InvoiceStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->canReceiveCreditNote());
    }

    public function test_creditable_values_match_can_receive_credit_note(): void
    {
        $fromValues = InvoiceStatus::creditableValues();
        $fromMethod = array_values(array_map(
            fn (InvoiceStatus $status): string => $status->value,
            array_filter(InvoiceStatus::cases(), fn (InvoiceStatus $status): bool => $status->canReceiveCreditNote()),
        ));

        $this->assertSame($fromMethod, $fromValues);
    }
}
