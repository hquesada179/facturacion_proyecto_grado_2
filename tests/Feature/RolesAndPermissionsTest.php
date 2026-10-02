<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_can_access_company_settings(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        $this->actingAs($user)->get('/configuracion/empresa')->assertOk();
    }

    public function test_facturador_cannot_access_company_settings(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $this->actingAs($user)->get('/configuracion/empresa')->assertForbidden();
    }

    public function test_contador_cannot_access_company_settings(): void
    {
        $user = User::factory()->for(Company::factory())->contador()->create();

        $this->actingAs($user)->get('/configuracion/empresa')->assertForbidden();
    }

    public function test_auditor_cannot_access_company_settings(): void
    {
        $user = User::factory()->for(Company::factory())->auditor()->create();

        $this->actingAs($user)->get('/configuracion/empresa')->assertForbidden();
    }

    public function test_facturador_can_access_invoicing_creation_screens(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $this->actingAs($user)->get('/clientes/nuevo')->assertOk();
    }

    public function test_contador_cannot_access_invoicing_creation_screens(): void
    {
        $user = User::factory()->for(Company::factory())->contador()->create();

        $this->actingAs($user)->get('/clientes/nuevo')->assertForbidden();
    }

    public function test_auditor_cannot_access_invoicing_creation_screens(): void
    {
        $user = User::factory()->for(Company::factory())->auditor()->create();

        $this->actingAs($user)->get('/clientes/nuevo')->assertForbidden();
    }

    public function test_contador_and_auditor_have_read_only_access_to_documents_and_reports(): void
    {
        $contador = User::factory()->for(Company::factory())->contador()->create();
        $auditor = User::factory()->for(Company::factory())->auditor()->create();

        $this->actingAs($contador)->get('/clientes')->assertOk();
        $this->actingAs($contador)->get('/facturas')->assertOk();
        $this->actingAs($contador)->get('/reportes')->assertOk();
        $this->actingAs($contador)->get('/dashboard')->assertOk();

        $this->actingAs($auditor)->get('/clientes')->assertOk();
        $this->actingAs($auditor)->get('/facturas')->assertOk();
        $this->actingAs($auditor)->get('/reportes')->assertOk();
        $this->actingAs($auditor)->get('/dashboard')->assertOk();
    }

    public function test_only_administrador_and_auditor_can_access_traceability(): void
    {
        $administrador = User::factory()->for(Company::factory())->administrador()->create();
        $auditor = User::factory()->for(Company::factory())->auditor()->create();
        $facturador = User::factory()->for(Company::factory())->facturador()->create();
        $contador = User::factory()->for(Company::factory())->contador()->create();

        $this->actingAs($administrador)->get('/trazabilidad')->assertOk();
        $this->actingAs($auditor)->get('/trazabilidad')->assertOk();
        $this->actingAs($facturador)->get('/trazabilidad')->assertForbidden();
        $this->actingAs($contador)->get('/trazabilidad')->assertForbidden();
    }
}
