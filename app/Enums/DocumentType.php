<?php

namespace App\Enums;

enum DocumentType: string
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Factura de venta',
            self::CreditNote => 'Nota crédito',
        };
    }
}
