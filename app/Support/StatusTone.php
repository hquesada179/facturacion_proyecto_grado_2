<?php

namespace App\Support;

/**
 * Single source of truth for status-label -> visual tone. Both
 * ui/badge.blade.php and ui/data-table.blade.php used to keep their own
 * copy of this whitelist; the two had drifted apart ("Emitida",
 * "Error técnico", "Rechazada (simulado)", "Enviando (simulado)",
 * "Validada localmente", "Descartada" and "Parcialmente abonada" were
 * only in the badge component's list), so a status recognized as a pill
 * in one table rendered as plain text in another. Centralizing here
 * means there is only ever one list to keep in sync with the app's
 * enums' label() methods.
 */
class StatusTone
{
    private const SUCCESS = ['Validada', 'Activo', 'Correcto', 'Aprobada', 'Registrada', 'Respondida', 'Finalizada', 'Vigente', 'Emitida', 'Validada localmente'];

    private const WARNING = ['Pendiente', 'Por validar', 'En revisión', 'Borrador', 'Por vencer', 'Enviando (simulado)'];

    private const ERROR = ['Con error', 'Agotada', 'Rechazada (simulado)', 'Error técnico'];

    private const NEUTRAL = ['Anulada', 'Inactivo', 'Inactiva', 'Descontinuado', 'Descartada', 'Parcialmente abonada'];

    public static function classes(string $status): string
    {
        return match (true) {
            in_array($status, self::SUCCESS, true) => 'bg-success-soft text-success-soft-foreground',
            in_array($status, self::WARNING, true) => 'bg-warning-soft text-warning-soft-foreground',
            in_array($status, self::ERROR, true) => 'bg-error-soft text-error-soft-foreground',
            in_array($status, self::NEUTRAL, true) => 'bg-surface-variant text-on-surface-variant',
            default => 'bg-surface-container text-on-surface-variant',
        };
    }

    public static function isKnown(string $status): bool
    {
        return in_array($status, [...self::SUCCESS, ...self::WARNING, ...self::ERROR, ...self::NEUTRAL], true);
    }
}
