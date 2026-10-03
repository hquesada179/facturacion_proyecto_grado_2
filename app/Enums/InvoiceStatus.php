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
}
