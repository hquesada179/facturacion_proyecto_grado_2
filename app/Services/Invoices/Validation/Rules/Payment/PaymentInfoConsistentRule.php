<?php

namespace App\Services\Invoices\Validation\Rules\Payment;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;

/**
 * Both payment_type and payment_method stay optional for invoices that
 * never touch payment data at all (legacy drafts, and every flow outside
 * the wizard that doesn't model payment terms) — that combination never
 * fires here. But once either one is set, leaving the other on "Sin
 * definir" is an inconsistent, half-filled state that must block
 * emission: you cannot say *when* you'll be paid without also saying
 * *how*, or vice versa.
 */
final class PaymentInfoConsistentRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $results = [];

        if ($context->paymentType !== null && $context->paymentMethod === null) {
            $results[] = new ValidationResult(
                codigo: 'PRO-PAYMENT-003',
                regla: 'Método de pago requerido',
                severidad: ValidationSeverity::Bloqueo,
                campo: 'payment_method',
                mensaje: 'Ya definiste la condición de pago; también debes indicar el método de pago antes de emitir.',
                sugerencia: 'Selecciona el método de pago en el resumen.',
            );
        }

        if ($context->paymentMethod !== null && $context->paymentType === null) {
            $results[] = new ValidationResult(
                codigo: 'PRO-PAYMENT-004',
                regla: 'Condición de pago requerida',
                severidad: ValidationSeverity::Bloqueo,
                campo: 'payment_type',
                mensaje: 'Ya definiste el método de pago; también debes indicar si la factura es de contado o a crédito.',
                sugerencia: 'Selecciona la condición de pago en el resumen.',
            );
        }

        return $results;
    }
}
