<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\NumberingResolution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NumberingResolution>
 */
class NumberingResolutionFactory extends Factory
{
    protected $model = NumberingResolution::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'document_type' => DocumentType::Invoice->value,
            'authorization_number_simulated' => fake()->unique()->numerify('SIM-########'),
            'prefix' => 'FV',
            'range_from' => 1,
            'range_to' => 5000,
            'current_consecutive' => 1,
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'simulated_technical_key' => fake()->unique()->sha256(),
            'is_active' => true,
        ];
    }

    public function creditNote(): static
    {
        return $this->state(fn (array $attributes) => [
            'document_type' => DocumentType::CreditNote->value,
            'prefix' => 'NC',
        ]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => now()->subYear()->toDateString(),
            'valid_until' => now()->addDays(10)->toDateString(),
        ]);
    }

    public function nearlyExhausted(): static
    {
        return $this->state(fn (array $attributes) => [
            'range_from' => 1,
            'range_to' => 100,
            'current_consecutive' => 95,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
