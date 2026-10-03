<?php

namespace App\Services\Invoices\Validation\Rules\Customer;

use App\Services\Customers\CustomerService;
use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;

final class CustomerFinalConsumerConfiguredRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $customer = $context->customer;

        if ($customer === null || ! $customer->is_final_consumer) {
            return [];
        }

        if ($customer->identification_number === CustomerService::FINAL_CONSUMER_IDENTIFICATION) {
            return [];
        }

        return [new ValidationResult(
            codigo: 'PRO-CUSTOMER-005',
            regla: 'Consumidor final configurado correctamente',
            severidad: ValidationSeverity::Bloqueo,
            campo: 'customer.identification_number',
            mensaje: 'El cliente marcado como consumidor final no tiene la identificación ficticia esperada.',
            sugerencia: 'Restaura el registro de "Consumidor Final" a su identificación original o desmarca la bandera.',
        )];
    }
}
