<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrador = 'administrador';
    case Facturador = 'facturador';
    case Contador = 'contador';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::Facturador => 'Facturador',
            self::Contador => 'Contador',
            self::Auditor => 'Auditor',
        };
    }
}
