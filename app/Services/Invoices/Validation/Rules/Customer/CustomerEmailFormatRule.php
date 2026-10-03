<?php

namespace App\Services\Invoices\Validation\Rules\Customer;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;

final class CustomerEmailFormatRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $customer = $context->customer;

        if ($customer === null || blank($customer->email)) {
            return [];
        }

        if (filter_var($customer->email, FILTER_VALIDATE_EMAIL) !== false) {
            return [];
        }

        return [new ValidationResult(
            codigo: 'PRO-CUSTOMER-007',
            regla: 'Correo con formato válido',
            severidad: ValidationSeverity::Advertencia,
            campo: 'customer.email',
            mensaje: 'El correo del cliente no tiene un formato válido.',
            sugerencia: 'Corrige el correo electrónico del cliente.',
        )];
    }
}
