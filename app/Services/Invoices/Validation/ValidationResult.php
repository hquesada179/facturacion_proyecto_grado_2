<?php

namespace App\Services\Invoices\Validation;

use JsonSerializable;

/**
 * Codes are internal to this prototype (PRO-<DOMINIO>-<NNN>), not official
 * DIAN validation codes — we never fabricate an official code we haven't
 * confirmed.
 */
final readonly class ValidationResult implements JsonSerializable
{
    public function __construct(
        public string $codigo,
        public string $regla,
        public ValidationSeverity $severidad,
        public string $campo,
        public string $mensaje,
        public string $sugerencia,
    ) {}

    public function toArray(): array
    {
        return [
            'codigo' => $this->codigo,
            'regla' => $this->regla,
            'severidad' => $this->severidad->value,
            'campo' => $this->campo,
            'mensaje' => $this->mensaje,
            'sugerencia' => $this->sugerencia,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
