<?php

namespace App\Services\Invoices\Validation\Rules\Items;

use App\Enums\ProductStatus;
use App\Services\Invoices\Validation\Rules\RuleInterface;
use App\Services\Invoices\Validation\ValidationContext;
use App\Services\Invoices\Validation\ValidationResult;
use App\Services\Invoices\Validation\ValidationSeverity;

final class ProductIsActiveRule implements RuleInterface
{
    public function check(ValidationContext $context): array
    {
        $results = [];

        foreach ($context->lines as $line) {
            if ($line->product === null || $line->product->status !== ProductStatus::Inactive) {
                continue;
            }

            $results[] = new ValidationResult(
                codigo: 'PRO-ITEMS-003',
                regla: 'Producto activo',
                severidad: ValidationSeverity::Bloqueo,
                campo: "items.{$line->index}.product_service_id",
                mensaje: 'El producto o servicio está inactivo.',
                sugerencia: 'Activa el producto o selecciona uno distinto.',
            );
        }

        return $results;
    }
}
