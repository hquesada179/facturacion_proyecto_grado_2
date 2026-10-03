<?php

namespace Tests\Unit\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Services\CreditNotes\CreditNoteStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreditNoteStateMachineTest extends TestCase
{
    public static function validTransitions(): array
    {
        return [
            'draft -> locally_validated' => [CreditNoteStatus::Draft, CreditNoteStatus::LocallyValidated],
            'draft -> discarded' => [CreditNoteStatus::Draft, CreditNoteStatus::Discarded],
            'locally_validated -> sending_simulated' => [CreditNoteStatus::LocallyValidated, CreditNoteStatus::SendingSimulated],
            'sending_simulated -> issued' => [CreditNoteStatus::SendingSimulated, CreditNoteStatus::Issued],
            'sending_simulated -> simulated_rejected' => [CreditNoteStatus::SendingSimulated, CreditNoteStatus::SimulatedRejected],
            'sending_simulated -> technical_error' => [CreditNoteStatus::SendingSimulated, CreditNoteStatus::TechnicalError],
        ];
    }

    #[DataProvider('validTransitions')]
    public function test_valid_transition_is_allowed(CreditNoteStatus $from, CreditNoteStatus $to): void
    {
        $creditNote = new CreditNote;
        $creditNote->status = $from;

        $this->assertTrue(CreditNoteStateMachine::canTransition($from, $to));

        CreditNoteStateMachine::transition($creditNote, $to);

        $this->assertSame($to, $creditNote->status);
    }

    public function test_issued_note_is_not_editable(): void
    {
        $this->assertFalse(CreditNoteStateMachine::isEditable(CreditNoteStatus::Issued));
        $this->assertTrue(CreditNoteStateMachine::isEditable(CreditNoteStatus::Draft));
    }
}
