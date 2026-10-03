<?php

namespace App\Services\Invoices\Validation\Rules\Items;

use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;

final class ProductExistsRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $results = [];

        foreach ($context->lines as $line) {
            if (! $line->productReferenced || $line->product !== null) {
                continue;
            }

            $results[] = new ValidationResult(
                codigo: 'PRO-ITEMS-002',
                regla: 'Producto existente',
                severidad: ValidationSeverity::Bloqueo,
                campo: "items.{$line->index}.product_service_id",
                mensaje: 'El producto o servicio seleccionado no existe.',
                sugerencia: 'Selecciona un producto o servicio válido para esta línea.',
            );
        }

        return $results;
    }
}
