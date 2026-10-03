<?php

namespace App\Services\Invoices\Validation\Rules\Payment;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;

/** Dormant until due_date/issue_date are both supplied. */
final class DueDateNotBeforeIssueDateRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        if ($context->dueDate === null || $context->issueDate === null) {
            return [];
        }

        if (! $context->dueDate->lt($context->issueDate)) {
            return [];
        }

        return [new ValidationResult(
            codigo: 'PRO-PAYMENT-002',
            regla: 'Vencimiento no anterior a la emisión',
            severidad: ValidationSeverity::Bloqueo,
            campo: 'due_date',
            mensaje: 'La fecha de vencimiento no puede ser anterior a la fecha de emisión.',
            sugerencia: 'Ajusta la fecha de vencimiento.',
        )];
    }
}
