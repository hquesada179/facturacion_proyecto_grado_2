<?php

namespace App\Services\Products;

use App\Models\ProductService;

class ProductCatalogService
{
    /**
     * Shared by the standalone product form and the invoice wizard's
     * "quick create" step, so creating a product plus its tax selection
     * never drifts between the two entry points.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $taxIds
     */
    public function createFromData(array $data, array $taxIds): ProductService
    {
        $product = ProductService::create($this->normalizeInventoryData($data));
        $product->taxes()->sync($taxIds);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $taxIds
     */
    public function updateFromData(ProductService $product, array $data, array $taxIds): ProductService
    {
        $product->update($this->normalizeInventoryData($data));
        $product->taxes()->sync($taxIds);

        return $product;
    }

    /**
     * A service can never carry inventory — force both fields back to
     * their "not inventoried" state regardless of what was submitted, so
     * a stray is_inventoried=1 on a service (bypassed client-side check,
     * stale payload, etc.) can never slip through either entry point.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeInventoryData(array $data): array
    {
        if (($data['type'] ?? null) !== 'product') {
            $data['is_inventoried'] = false;
        }

        if (empty($data['is_inventoried'])) {
            $data['stock_quantity'] = null;
        }

        return $data;
    }
}
