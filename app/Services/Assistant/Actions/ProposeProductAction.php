<?php

namespace App\Services\Assistant\Actions;

use App\Enums\ProductStatus;
use App\Models\ProductService;
use App\Models\Tax;
use App\Models\User;
use App\Rules\ValidTaxCombination;
use App\Support\ReportFormatter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Interpretation + validation + pure preview for "Prepara un producto
 * Camisa Azul por $100.000 con IVA 19%" — never saves a ProductService
 * row. The IVA rate stated in the message only ever selects WHICH
 * already-configured Tax row to attach (by rate, scoped to this
 * company or global); the assistant never invents a tax or a rate.
 */
class ProposeProductAction
{
    /**
     * @param  array{name: ?string, price: ?string, tax_rate: ?float, exempt: bool, type: ?string}  $intent
     * @return array<string, mixed>
     */
    public function handle(User $user, array $intent): array
    {
        if (Gate::forUser($user)->denies('create', ProductService::class)) {
            return ['status' => 'denied', 'reason' => 'Tu rol no tiene permiso para crear productos o servicios.'];
        }

        $missing = [];

        if (! $intent['name']) {
            $missing[] = 'nombre';
        }

        if ($intent['price'] === null) {
            $missing[] = 'precio';
        }

        if ($missing !== []) {
            return [
                'status' => 'missing',
                'missing_fields' => $missing,
                'reason' => 'Necesito el '.implode(' y el ', $missing).' del producto o servicio.',
            ];
        }

        $taxIds = [];

        if (! $intent['exempt'] && $intent['tax_rate'] !== null) {
            $tax = Tax::query()->currentlyValid()->availableFor($user->company_id)
                ->where('rate', $intent['tax_rate'])
                ->first();

            if ($tax === null) {
                return [
                    'status' => 'invalid',
                    'reason' => "No encontré un impuesto IVA {$intent['tax_rate']}% configurado para tu empresa.",
                ];
            }

            $taxIds = [$tax->id];
        }

        $data = [
            'sku' => $this->generateSku($user, $intent['name']),
            'name' => $intent['name'],
            'description' => null,
            'category' => null,
            'type' => $intent['type'] ?? 'product',
            'unit' => $intent['type'] === 'service' ? 'servicio' : 'unidad',
            'price' => $intent['price'],
            'status' => ProductStatus::Active->value,
            'tax_included' => false,
            'is_inventoried' => false,
            'stock_quantity' => null,
            'taxes' => $taxIds,
        ];

        $errors = $this->validate($user, $data);

        if ($errors !== []) {
            return ['status' => 'invalid', 'reason' => implode(' ', $errors)];
        }

        $taxLabel = $intent['exempt'] ? 'Exento' : ($taxIds !== [] ? 'IVA '.$intent['tax_rate'].'%' : 'Sin impuestos');

        $preview = [
            'title' => 'Preparar producto o servicio',
            'summary' => $data['name'].' — '.ReportFormatter::money((float) $data['price']).' ('.$taxLabel.')',
            'fields' => [
                ['label' => 'Nombre', 'value' => $data['name']],
                ['label' => 'SKU / código', 'value' => $data['sku']],
                ['label' => 'Tipo', 'value' => $data['type'] === 'service' ? 'Servicio' : 'Producto'],
                ['label' => 'Precio', 'value' => ReportFormatter::money((float) $data['price'])],
                ['label' => 'Impuesto', 'value' => $taxLabel],
                ['label' => 'Unidad', 'value' => $data['unit']],
                ['label' => 'Precio incluye impuesto', 'value' => 'No'],
            ],
            'lines' => [],
            'totals' => [],
            'warnings' => [],
        ];

        return ['status' => 'ready', 'payload' => $data, 'preview' => $preview];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public function validate(User $user, array $data): array
    {
        $validator = Validator::make($data, [
            'sku' => ['required', 'string', 'max:50', Rule::unique('product_services')->where('company_id', $user->company_id)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['product', 'service'])],
            'unit' => ['required', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(array_map(fn (ProductStatus $status) => $status->value, ProductStatus::cases()))],
            'taxes' => ['nullable', 'array', new ValidTaxCombination],
            'taxes.*' => ['integer', 'exists:taxes,id'],
        ]);

        return $validator->fails() ? $validator->errors()->all() : [];
    }

    private function generateSku(User $user, string $name): string
    {
        $prefix = Str::of($name)->upper()->replaceMatches('/[^A-Z0-9]+/', '-')->trim('-')->limit(10, '');
        $prefix = $prefix->value() !== '' ? $prefix->value() : 'PROD';

        do {
            $candidate = $prefix.'-'.random_int(1000, 9999);
            $exists = ProductService::withoutGlobalScopes()
                ->where('company_id', $user->company_id)
                ->where('sku', $candidate)
                ->exists();
        } while ($exists);

        return $candidate;
    }
}
