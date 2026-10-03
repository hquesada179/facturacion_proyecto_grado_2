<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'person_type' => 'natural',
            'identification_type' => 'CC',
            'identification_number' => fake()->unique()->numerify('1##########'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('3## #######'),
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Bogotá D.C.', 'Medellín', 'Bucaramanga', 'Cali']),
            'department' => fake()->randomElement(['Santander', 'Antioquia', 'Cundinamarca', 'Valle del Cauca']),
            'country' => 'Colombia',
            'status' => 'active',
            'is_final_consumer' => false,
        ];
    }

    public function juridica(): static
    {
        return $this->state(fn (array $attributes) => [
            'person_type' => 'juridica',
            'identification_type' => 'NIT',
            'identification_number' => fake()->unique()->numerify('9########'),
            'name' => fake()->company(),
        ]);
    }

    public function finalConsumer(): static
    {
        return $this->state(fn (array $attributes) => [
            'identification_type' => 'CC',
            'identification_number' => '0000000000',
            'name' => 'Consumidor Final',
            'is_final_consumer' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'inactive']);
    }
}
