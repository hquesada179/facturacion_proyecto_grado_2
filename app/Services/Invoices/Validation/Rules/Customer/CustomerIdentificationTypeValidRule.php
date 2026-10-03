<?php

namespace App\Services\Invoices\Validation\Rules\Customer;

use App\Models\Customer;
use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;

final class CustomerIdentificationTypeValidRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $customer = $context->customer;

        if ($customer === null || array_key_exists($customer->identification_type, Customer::IDENTIFICATION_TYPES)) {
            return [];
        }

        return [new ValidationResult(
            codigo: 'PRO-CUSTOMER-003',
            regla: 'Tipo de identificación válido',
            severidad: ValidationSeverity::Bloqueo,
            campo: 'customer.identification_type',
            mensaje: 'El tipo de identificación del cliente no es válido.',
            sugerencia: 'Actualiza el cliente con un tipo de identificación reconocido (CC, CE, NIT, PAS, TI, RC).',
        )];
    }
}
