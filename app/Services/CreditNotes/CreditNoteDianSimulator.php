<?php

namespace App\Services\CreditNotes;

use App\Models\CreditNote;
use App\Services\Invoices\DianSimulationResult;
use InvalidArgumentException;

class CreditNoteDianSimulator
{
    public const SCENARIOS = ['validada', 'rechazada', 'error'];

    public function simulate(CreditNote $creditNote, ?string $forceScenario = null): DianSimulationResult
    {
        $scenario = $forceScenario ?? 'validada';

        if (! in_array($scenario, self::SCENARIOS, true)) {
            throw new InvalidArgumentException("Escenario simulado desconocido: {$scenario}");
        }

        return match ($scenario) {
            'validada' => new DianSimulationResult(
                status: 'validada',
                message: 'Documento de prueba – sin validez tributaria. Nota crédito validada en simulación.',
            ),
            'rechazada' => new DianSimulationResult(
                status: 'rechazada',
                message: 'Documento de prueba – sin validez tributaria. Nota crédito rechazada en simulación.',
            ),
            'error' => new DianSimulationResult(
                status: 'error',
                message: 'Documento de prueba – sin validez tributaria. Error técnico simulado al validar la nota crédito.',
            ),
        };
    }
}
