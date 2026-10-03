<?php

namespace App\Services\Invoices\Validation\Rules\Items;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;

final class AtLeastOneLineRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        if (count($context->lines) > 0) {
            return [];
        }

        return [new ValidationResult(
            codigo: 'PRO-ITEMS-001',
            regla: 'Al menos una línea',
            severidad: ValidationSeverity::Bloqueo,
            campo: 'items',
            mensaje: 'La factura no tiene productos ni servicios agregados.',
            sugerencia: 'Agrega al menos un producto o servicio antes de continuar.',
        )];
    }
}
