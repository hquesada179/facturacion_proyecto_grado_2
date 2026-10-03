<?php

namespace App\Enums;

enum NumberingResolutionStatus: string
{
    case Vigente = 'vigente';
    case Vencida = 'vencida';
    case Agotada = 'agotada';
    case Inactiva = 'inactiva';

    public function label(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::Vencida => 'Vencida',
            self::Agotada => 'Agotada',
            self::Inactiva => 'Inactiva',
        };
    }
}
