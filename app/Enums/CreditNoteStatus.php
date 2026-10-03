<?php

namespace App\Enums;

enum CreditNoteStatus: string
{
    case Draft = 'draft';
    case LocallyValidated = 'locally_validated';
    case SendingSimulated = 'sending_simulated';
    case SimulatedRejected = 'simulated_rejected';
    case Issued = 'issued';
    case TechnicalError = 'technical_error';
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
            self::Discarded => 'Descartada',
        };
    }
}
