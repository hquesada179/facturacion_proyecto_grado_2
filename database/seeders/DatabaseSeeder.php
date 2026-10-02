<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with a single demo company and one
     * user per role. These are development-only, clearly fictitious
     * accounts (facturapro.test domain) — never real credentials.
     */
    public function run(): void
    {
        $company = Company::factory()->create([
            'name' => 'FacturaPro Demo SAS',
            'legal_name' => 'FacturaPro Demo SAS',
            'nit' => '900000111-2',
            'email' => 'contacto@facturapro.test',
            'city' => 'Bucaramanga',
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
    }
}
