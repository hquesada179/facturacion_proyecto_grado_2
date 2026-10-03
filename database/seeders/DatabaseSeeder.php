<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Services\Customers\CustomerService;
use App\Services\Tax\NitDvCalculator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with the global tax catalog, a
     * single demo company and one user per role. These are
     * development-only, clearly fictitious accounts (facturapro.test
     * domain) — never real credentials.
     */
    public function run(): void
    {
        $this->call(TaxSeeder::class);

        $nit = '900000111';

        $company = Company::factory()->create([
            'name' => 'FacturaPro Demo SAS',
            'legal_name' => 'FacturaPro Demo SAS',
            'person_type' => 'juridica',
            'nit' => $nit,
            'nit_dv' => (string) NitDvCalculator::calculate($nit),
            'email' => 'contacto@facturapro.test',
            'city' => 'Bucaramanga',
            'department' => 'Santander',
            'country' => 'Colombia',
            'currency' => 'COP',
            'is_test_environment' => true,
            'fiscal_responsibilities' => ['O-48'],
        ]);

        User::factory()->administrador()->create([
            'company_id' => $company->id,
            'name' => 'Administrador Demo',
            'email' => 'admin@facturapro.test',
        ]);

        User::factory()->facturador()->create([
            'company_id' => $company->id,
            'name' => 'Facturador Demo',
            'email' => 'facturador@facturapro.test',
        ]);

        User::factory()->contador()->create([
            'company_id' => $company->id,
            'name' => 'Contador Demo',
            'email' => 'contador@facturapro.test',
        ]);

        User::factory()->auditor()->create([
            'company_id' => $company->id,
            'name' => 'Auditor Demo',
            'email' => 'auditor@facturapro.test',
        ]);

        app(CustomerService::class)->ensureFinalConsumer($company);
    }
}
