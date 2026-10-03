<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationEngine;
use InvalidArgumentException;

/**
 * Stands in for the real DIAN web service — it never makes a network
 * call. Every result is explicitly a simulation; callers must surface
 * "Validación simulada" / "Documento de prueba – sin validez tributaria"
 * wherever this result reaches the UI.
 */
class DianSimulator
{
    public const SCENARIOS = ['validada', 'rechazada', 'error'];

    public function __construct(private readonly ValidationEngine $engine) {}

    /**
     * @param  'validada'|'rechazada'|'error'|null  $forceScenario  Lets tests
     *                                                              (and, in principle, a future "simulate this scenario" admin
     *                                                              tool) exercise every branch deterministically. In normal
     *                                                              operation this is left null and the outcome is derived from the
     *                                                              invoice's own validation state.
     */
    public function simulate(Invoice $invoice, ValidationContext $context, ?string $forceScenario = null): DianSimulationResult
    {
        if ($forceScenario !== null) {
            if (! in_array($forceScenario, self::SCENARIOS, true)) {
                throw new InvalidArgumentException("Escenario simulado desconocido: {$forceScenario}");
            }

            return $this->resultFor($invoice, $forceScenario);
        }

        $results = $this->engine->run($context);

        // Defensive: emission should already be gated on zero blocking
        // issues before this is ever called.
        $scenario = $results->hasBlocking() ? 'rechazada' : 'validada';

        return $this->resultFor($invoice, $scenario);
    }

    private function resultFor(Invoice $invoice, string $scenario): DianSimulationResult
    {
        return match ($scenario) {
            'validada' => new DianSimulationResult(
                status: 'validada',
                message: 'Documento de prueba – sin validez tributaria. Validación simulada aprobada.',
            ),
            'rechazada' => new DianSimulationResult(
                status: 'rechazada',
                message: 'Documento de prueba – sin validez tributaria. Validación simulada rechazada.',
            ),
            'error' => new DianSimulationResult(
                status: 'error',
                message: 'Documento de prueba – sin validez tributaria. Error técnico simulado al intentar validar.',
            ),
        };
    }
}
