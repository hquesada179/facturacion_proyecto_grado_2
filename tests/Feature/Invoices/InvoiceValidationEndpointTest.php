<?php

namespace Tests\Feature\Invoices;

use App\Models\Company;
use App\Models\Customer;
use App\Models\ProductService;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceValidationEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_reach_the_validation_screen_or_endpoint(): void
    {
        $this->get(route('invoices.create.validation'))->assertRedirect(route('login'));
        $this->postJson(route('invoices.validate'), [])->assertUnauthorized();
    }

    public function test_contador_and_auditor_cannot_validate_drafts(): void
    {
        $contador = User::factory()->for(Company::factory())->contador()->create();
        $auditor = User::factory()->for(Company::factory())->auditor()->create();

        $this->actingAs($contador)->get(route('invoices.create.validation'))->assertForbidden();
        $this->actingAs($auditor)->postJson(route('invoices.validate'), [])->assertForbidden();
    }

    public function test_facturador_can_validate_a_valid_draft_with_no_blocking_results(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();
        $customer = Customer::factory()->for($user->company)->create(['status' => 'active']);
        $tax = Tax::factory()->create(['code' => 'IVA19', 'rate' => '19']);
        $product = ProductService::factory()->for($user->company)->create(['price' => '100000.00']);
        $product->taxes()->attach($tax->id);

        $response = $this->actingAs($user)->postJson(route('invoices.validate'), [
            'customer_id' => $customer->id,
            'items' => [[
                'product_service_id' => $product->id,
                'quantity' => '1',
                'unit_price' => '100000',
            ]],
        ]);

        $response->assertOk();
        $response->assertJsonPath('validation.ok', true);
        $response->assertJsonPath('calculation.total', '119000.00');
    }

    public function test_server_recalculates_and_ignores_client_supplied_totals(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();
        $customer = Customer::factory()->for($user->company)->create(['status' => 'active']);
        $product = ProductService::factory()->for($user->company)->create(['price' => '100000.00']);

        $response = $this->actingAs($user)->postJson(route('invoices.validate'), [
            'customer_id' => $customer->id,
            // These do not exist as validated/fillable fields and must be
            // silently ignored — the server is the only source of truth.
            'subtotal' => '1',
            'tax_total' => '1',
            'total' => '1',
            'status' => 'emitida',
            'items' => [[
                'product_service_id' => $product->id,
                'quantity' => '1',
                'unit_price' => '100000',
            ]],
        ]);

        $response->assertOk();
        $response->assertJsonPath('calculation.total', '100000.00');
        $response->assertJsonMissingPath('status');
    }

    public function test_draft_with_blocking_issues_is_reported_but_not_rejected_as_422(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $response = $this->actingAs($user)->postJson(route('invoices.validate'), [
            'customer_id' => null,
            'items' => [],
        ]);

        $response->assertOk();
        $response->assertJsonPath('validation.ok', false);
        $this->assertGreaterThan(0, $response->json('validation.blocking_count'));
    }

    public function test_company_isolation_customer_from_another_company_is_not_found(): void
    {
        $companyB = Company::factory()->create();
        $otherCustomer = Customer::factory()->for($companyB)->create();

        $user = User::factory()->for(Company::factory())->facturador()->create();

        $response = $this->actingAs($user)->postJson(route('invoices.validate'), [
            'customer_id' => $otherCustomer->id,
            'items' => [],
        ]);

        $response->assertOk();
        $this->assertContains('PRO-CUSTOMER-001', array_column($response->json('validation.results'), 'codigo'));
    }

    public function test_validation_screen_renders_for_administrador(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();

        $this->actingAs($user)->get(route('invoices.create.validation'))->assertOk();
    }
}
