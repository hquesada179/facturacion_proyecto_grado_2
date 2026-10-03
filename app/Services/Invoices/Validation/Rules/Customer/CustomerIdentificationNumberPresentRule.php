<?php

namespace App\Services\Invoices\Validation\Rules\Customer;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;

final class CustomerIdentificationNumberPresentRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $customer = $context->customer;

        if ($customer === null || trim((string) $customer->identification_number) !== '') {
            return [];
        }

        return [new ValidationResult(
            codigo: 'PRO-CUSTOMER-004',
            regla: 'Número de identificación presente',
            severidad: ValidationSeverity::Bloqueo,
            campo: 'customer.identification_number',
            mensaje: 'El cliente no tiene número de identificación.',
            sugerencia: 'Completa el número de identificación del cliente.',
        )];
    }
}
