<?php

namespace Database\Factories;

use App\Models\Company;
use App\Services\Tax\NitDvCalculator;
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
        $nit = fake()->unique()->numerify('9########');

        return [
            'name' => fake()->company(),
            'legal_name' => fake()->company().' S.A.S.',
            'person_type' => 'juridica',
            'nit' => $nit,
            'nit_dv' => (string) NitDvCalculator::calculate($nit),
            'email' => fake()->unique()->userName().'@facturapro.test',
            'phone' => '+57 601 555 '.fake()->numerify('####'),
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Bogotá D.C.', 'Medellín', 'Bucaramanga', 'Cali']),
            'department' => fake()->randomElement(['Santander', 'Antioquia', 'Cundinamarca', 'Valle del Cauca']),
            'country' => 'Colombia',
            'tax_regime' => 'Responsable de IVA',
            'fiscal_responsibilities' => ['O-48'],
            'currency' => 'COP',
            'is_test_environment' => true,
            'invoice_prefix' => 'FV',
            'simulation_enabled' => true,
        ];
    }
}
