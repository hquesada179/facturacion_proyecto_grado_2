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
        $product = ProductService::create($data);
        $product->taxes()->sync($taxIds);

        return $product;
    }
}
