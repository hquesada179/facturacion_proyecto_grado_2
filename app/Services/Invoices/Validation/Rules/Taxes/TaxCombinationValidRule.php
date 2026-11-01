<?php

namespace App\Services\Invoices\Validation\Rules\Taxes;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;
use App\Support\TaxCombinationPolicy;

/**
 * Defense in depth: App\Rules\ValidTaxCombination already blocks an
 * absurd tax combination (two IVA-nature taxes, or Excluido with an
 * IVA-nature tax) at the moment the product or the line is submitted.
 * This re-checks the same policy against the line's final, resolved
 * tax list so a draft can never reach emission with that combination,
 * regardless of how its taxes got there.
 */
final class TaxCombinationValidRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $results = [];

        foreach ($context->lines as $line) {
            $violation = TaxCombinationPolicy::violationFor($line->taxes);

            if ($violation === null) {
                continue;
            }

            $results[] = new ValidationResult(
                codigo: 'PRO-TAX-005',
                regla: 'Combinación de impuestos inválida',
                severidad: ValidationSeverity::Bloqueo,
                campo: "items.{$line->index}.taxes",
                mensaje: $violation,
                sugerencia: 'Ajusta los impuestos seleccionados en esta línea.',
            );
        }

        return $results;
    }
}
