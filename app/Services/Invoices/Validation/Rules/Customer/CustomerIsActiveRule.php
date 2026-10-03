<?php

namespace App\Services\Invoices\Validation\Rules\Customer;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;

final class CustomerIsActiveRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        if ($context->customer === null || $context->customer->status === 'active') {
            return [];
        }

        return [new ValidationResult(
            codigo: 'PRO-CUSTOMER-002',
            regla: 'Cliente activo',
            severidad: ValidationSeverity::Bloqueo,
            campo: 'customer_id',
            mensaje: 'El cliente seleccionado está inactivo.',
            sugerencia: 'Activa el cliente o selecciona uno distinto antes de continuar.',
        )];
    }
}
