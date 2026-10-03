<?php

namespace Database\Factories;

use App\Models\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tax>
 */
class TaxFactory extends Factory
{
    protected $model = Tax::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => null,
            'code' => fake()->unique()->bothify('TAX-###'),
            'name' => 'IVA '.fake()->randomElement([19, 5, 0]).'%',
            'rate' => fake()->randomElement([19, 5, 0]),
            'type' => 'vat',
            'calculation_type' => 'percentage',
            'nature' => 'IVA',
            'condition' => 'gravado',
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
