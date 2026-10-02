<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'legal_name' => fake()->company().' S.A.S.',
            'nit' => fake()->unique()->numerify('9########-#'),
            'email' => fake()->unique()->userName().'@facturapro.test',
            'phone' => '+57 601 555 '.fake()->numerify('####'),
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Bogotá D.C.', 'Medellín', 'Bucaramanga', 'Cali']),
            'tax_regime' => 'Responsable de IVA',
            'invoice_prefix' => 'FV',
            'simulation_enabled' => true,
        ];
    }
}
