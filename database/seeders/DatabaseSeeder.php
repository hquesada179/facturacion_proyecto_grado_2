<?php

namespace Database\Seeders;

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\NumberingResolution;
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

        $this->createDemoInvoiceResolution($company);
    }

    private function createDemoInvoiceResolution(Company $company): void
    {
        $resolution = NumberingResolution::query()
            ->withoutGlobalScopes()
            ->firstOrNew([
                'company_id' => $company->id,
                'document_type' => DocumentType::Invoice->value,
                'prefix' => 'FV',
            ]);

        $resolution->authorization_number_simulated = 'SIM-DEMO-FV-000001';
        $resolution->range_from = 1;
        $resolution->range_to = 999999;
        $resolution->valid_from = now()->subDay()->toDateString();
        $resolution->valid_until = now()->addYears(5)->toDateString();
        $resolution->simulated_technical_key = 'DOCUMENTO-DE-PRUEBA-SIN-VALIDEZ-TRIBUTARIA';
        $resolution->is_active = true;

        if (! $resolution->exists) {
            $resolution->current_consecutive = 1;
        }

        $resolution->status = $resolution->determineStatus();
        $resolution->save();
    }
}
