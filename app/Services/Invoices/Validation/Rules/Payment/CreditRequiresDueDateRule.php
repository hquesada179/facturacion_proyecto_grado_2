<?php

namespace App\Services\Invoices\Validation\Rules\Payment;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;

/**
 * Dormant until the invoice actually models payment terms (no
 * payment_type/due_date column yet): with paymentType null this never
 * fires. Kept here, tested, and ready for when that structure lands.
 */
final class CreditRequiresDueDateRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        if ($context->paymentType !== 'credito' || $context->dueDate !== null) {
            return [];
        }

        return [new ValidationResult(
            codigo: 'PRO-PAYMENT-001',
            regla: 'Crédito requiere fecha de vencimiento',
            severidad: ValidationSeverity::Bloqueo,
            campo: 'due_date',
            mensaje: 'Una factura a crédito requiere fecha de vencimiento.',
            sugerencia: 'Ingresa la fecha de vencimiento del pago.',
        )];
    }
}
