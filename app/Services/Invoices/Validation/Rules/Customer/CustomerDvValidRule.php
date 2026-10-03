<?php

namespace App\Services\Invoices\Validation\Rules\Customer;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;
use App\Services\Tax\NitDvCalculator;

final class CustomerDvValidRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $customer = $context->customer;

        if ($customer === null || $customer->identification_type !== 'NIT') {
            return [];
        }

        if ($customer->dv !== null && NitDvCalculator::isValid($customer->identification_number, $customer->dv)) {
            return [];
        }

        return [new ValidationResult(
            codigo: 'PRO-CUSTOMER-006',
            regla: 'Dígito de verificación válido',
            severidad: ValidationSeverity::Bloqueo,
            campo: 'customer.dv',
            mensaje: 'El dígito de verificación del NIT del cliente no es válido.',
            sugerencia: 'Edita el cliente para recalcular el dígito de verificación.',
        )];
    }
}
