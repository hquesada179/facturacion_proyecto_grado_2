<?php

namespace App\Services\Assistant\Actions;

use App\Models\AssistantActionProposal;
use App\Models\ProductService;
use App\Models\User;
use App\Services\Products\ProductCatalogService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Confirm-time execution for `create_product`. Re-validates (SKU
 * uniqueness, tax combination) against the current database state
 * before calling the real domain service.
 */
class ExecuteProductAction
{
    public function __construct(
        private readonly ProposeProductAction $validator,
        private readonly ProductCatalogService $catalog,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, AssistantActionProposal $proposal): array
    {
        if (Gate::forUser($user)->denies('create', ProductService::class)) {
            throw ValidationException::withMessages(['action' => 'Tu rol no tiene permiso para crear productos o servicios.']);
        }

        $data = $proposal->payload;
        $taxIds = $data['taxes'] ?? [];
        unset($data['taxes']);

        $errors = $this->validator->validate($user, array_merge($data, ['taxes' => $taxIds]));

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'product' => 'La información cambió desde que se preparó la acción. Revisa una nueva vista previa.',
            ]);
        }

        $product = $this->catalog->createFromData($data, $taxIds);

        return [
            'resource_type' => 'product',
            'resource_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => (float) $product->price,
            'status' => $product->status->value,
        ];
    }
}
