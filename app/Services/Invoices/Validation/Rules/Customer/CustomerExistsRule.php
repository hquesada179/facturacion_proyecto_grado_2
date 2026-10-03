<?php

namespace App\Services\Invoices\Validation\Rules\Customer;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;

final class CustomerExistsRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        if ($context->customer !== null) {
            return [];
        }

        return [new ValidationResult(
            codigo: 'PRO-CUSTOMER-001',
            regla: 'Cliente existente',
            severidad: ValidationSeverity::Bloqueo,
            campo: 'customer_id',
            mensaje: 'El cliente no existe o no fue seleccionado.',
            sugerencia: 'Selecciona un cliente válido para la factura.',
        )];
    }
}
