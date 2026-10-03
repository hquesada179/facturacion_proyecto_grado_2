<?php

namespace Tests\Feature\Settings;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(): array
    {
        return [
            'name' => 'FacturaPro Demo SAS',
            'legal_name' => 'FacturaPro Demo SAS',
            'person_type' => 'juridica',
            'nit' => '900373115',
            'email' => 'contacto@facturapro.test',
            'address' => 'Calle 10 # 5-20',
            'city' => 'Bucaramanga',
            'department' => 'Santander',
            'country' => 'Colombia',
            'currency' => 'COP',
            'is_test_environment' => '1',
            'fiscal_responsibilities' => ['O-48'],
        ];
    }

    public function test_administrador_can_update_company_with_valid_data(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        $response = $this->actingAs($user)->put(route('settings.company.update'), $this->validPayload());

        $response->assertRedirect(route('settings.company'));

        $user->company->refresh();
        $this->assertSame('900373115', $user->company->nit);
        $this->assertSame('3', $user->company->nit_dv);
        $this->assertTrue($user->company->is_test_environment);
        $this->assertSame(['O-48'], $user->company->fiscal_responsibilities);
    }

    public function test_nit_without_digits_is_rejected(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        $payload = $this->validPayload();
        $payload['nit'] = 'abc';

        $response = $this->actingAs($user)->put(route('settings.company.update'), $payload);

        $response->assertSessionHasErrors('nit');
    }

    public function test_facturador_cannot_update_company_settings(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $this->actingAs($user)->get(route('settings.company'))->assertForbidden();
        $this->actingAs($user)->put(route('settings.company.update'), $this->validPayload())->assertForbidden();
    }

    public function test_contador_and_auditor_cannot_update_company_settings(): void
    {
        $contador = User::factory()->for(Company::factory())->contador()->create();
        $auditor = User::factory()->for(Company::factory())->auditor()->create();

        $this->actingAs($contador)->get(route('settings.company'))->assertForbidden();
        $this->actingAs($auditor)->get(route('settings.company'))->assertForbidden();
    }
}
