<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case LocallyValidated = 'locally_validated';
    case SendingSimulated = 'sending_simulated';
    case SimulatedRejected = 'simulated_rejected';
    case Issued = 'issued';
    case TechnicalError = 'technical_error';
    case PartiallyCredited = 'partially_credited';
    case Voided = 'voided';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::LocallyValidated => 'Validada localmente',
            self::SendingSimulated => 'Enviando (simulado)',
            self::SimulatedRejected => 'Rechazada (simulado)',
            self::Issued => 'Emitida',
            self::TechnicalError => 'Error técnico',
            self::PartiallyCredited => 'Parcialmente abonada',
            self::Voided => 'Anulada',
            self::Discarded => 'Descartada',
        };
    }

    /**
     * True for any status only reachable by having been issued at some
     * point (Issued itself, or one of its two only destinations in
     * InvoiceStateMachine: PartiallyCredited, Voided). The single source
     * of truth for "this invoice has — or once had — a valid definitive
     * PDF and emission data worth showing", so a later credit note
     * never hides a document's own history/PDF/trace log.
     */
    public function wasIssued(): bool
    {
        return match ($this) {
            self::Issued, self::PartiallyCredited, self::Voided => true,
            default => false,
        };
    }

    /**
     * True only while there can still be remaining balance to credit —
     * Voided has none left, everything else never had any to begin
     * with. Single source of truth for "a new credit note can target
     * this invoice", replacing the same literal pair that used to be
     * duplicated across CreditNoteController, CreditNoteDraftService
     * and CreditNoteValidationService.
     */
    public function canReceiveCreditNote(): bool
    {
        return match ($this) {
            self::Issued, self::PartiallyCredited => true,
            default => false,
        };
    }

    /**
     * Same statuses as canReceiveCreditNote(), as raw column values —
     * for the one spot (CreditNoteController's invoice picker) that
     * needs a DB-level whereIn() rather than a check against a single
     * already-loaded status.
     *
     * @return array<int, string>
     */
    public static function creditableValues(): array
    {
        return [self::Issued->value, self::PartiallyCredited->value];
    }
}
