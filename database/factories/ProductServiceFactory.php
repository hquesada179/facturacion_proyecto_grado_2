<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Company;
use App\Models\ProductService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductService>
 */
class ProductServiceFactory extends Factory
{
    protected $model = ProductService::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'sku' => fake()->unique()->bothify('PS-###??'),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(['product', 'service']),
            'unit' => 'unidad',
            'price' => fake()->randomFloat(2, 10000, 2000000),
            'status' => ProductStatus::Active->value,
            'tax_included' => false,
        ];
    }

    public function service(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'service', 'unit' => 'servicio']);
    }

    public function discontinued(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProductStatus::Discontinued->value]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProductStatus::Inactive->value]);
    }
}
