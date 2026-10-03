<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Customers\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'person_type' => 'natural',
            'identification_type' => 'CC',
            'identification_number' => '1020304050',
            'name' => 'Cliente de Prueba',
            'email' => 'cliente@example.com',
            'status' => 'active',
        ], $overrides);
    }

    public function test_facturador_can_create_a_customer(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $response = $this->actingAs($user)->post(route('customers.store'), $this->validPayload());

        $customer = Customer::first();
        $response->assertRedirect(route('customers.show', $customer));
        $this->assertDatabaseHas('customers', ['identification_number' => '1020304050', 'company_id' => $user->company_id]);
    }

    public function test_quick_creation_does_not_require_phone_or_address_for_natural_person(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $response = $this->actingAs($user)->post(route('customers.store'), $this->validPayload());

        $response->assertSessionDoesntHaveErrors(['phone', 'address']);
    }

    public function test_juridica_person_requires_phone_and_address(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $response = $this->actingAs($user)->post(route('customers.store'), $this->validPayload([
            'person_type' => 'juridica',
            'identification_type' => 'NIT',
            'identification_number' => '900373115',
        ]));

        $response->assertSessionHasErrors(['phone', 'address']);
    }

    public function test_nit_identification_gets_a_calculated_check_digit(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $this->actingAs($user)->post(route('customers.store'), $this->validPayload([
            'person_type' => 'juridica',
            'identification_type' => 'NIT',
            'identification_number' => '900373115',
            'phone' => '6015550000',
            'address' => 'Calle 1 # 2-3',
        ]));

        $this->assertDatabaseHas('customers', ['identification_number' => '900373115', 'dv' => '3']);
    }

    public function test_identification_number_must_be_unique_per_company(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();
        Customer::factory()->for($user->company)->create(['identification_number' => '1020304050']);

        $response = $this->actingAs($user)->post(route('customers.store'), $this->validPayload());

        $response->assertSessionHasErrors('identification_number');
    }

    public function test_same_identification_number_is_allowed_across_different_companies(): void
    {
        $companyA = Company::factory()->create();
        Customer::factory()->for($companyA)->create(['identification_number' => '1020304050']);

        $userB = User::factory()->for(Company::factory())->facturador()->create();

        $response = $this->actingAs($userB)->post(route('customers.store'), $this->validPayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('customers', ['company_id' => $userB->company_id, 'identification_number' => '1020304050']);
    }

    public function test_search_filters_customers_by_name_or_identification(): void
    {
        $user = User::factory()->for(Company::factory())->create();
        Customer::factory()->for($user->company)->create(['name' => 'Comercializadora Andina']);
        Customer::factory()->for($user->company)->create(['name' => 'Tienda La Esquina']);

        $response = $this->actingAs($user)->get(route('customers.index', ['q' => 'Andina']));

        $response->assertSee('Comercializadora Andina');
        $response->assertDontSee('Tienda La Esquina');
    }

    public function test_create_and_edit_forms_render_for_facturador(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();
        $customer = Customer::factory()->for($user->company)->create();

        $this->actingAs($user)->get(route('customers.create'))->assertOk();
        $this->actingAs($user)->get(route('customers.edit', $customer))->assertOk();
    }

    public function test_contador_and_auditor_have_read_only_access(): void
    {
        $contador = User::factory()->for(Company::factory())->contador()->create();
        Customer::factory()->for($contador->company)->create();

        $this->actingAs($contador)->get(route('customers.index'))->assertOk();
        $this->actingAs($contador)->get(route('customers.create'))->assertForbidden();
        $this->actingAs($contador)->post(route('customers.store'), $this->validPayload())->assertForbidden();
    }

    public function test_every_company_has_a_final_consumer_customer(): void
    {
        $company = Company::factory()->create();

        $finalConsumer = app(CustomerService::class)->ensureFinalConsumer($company);

        $this->assertTrue($finalConsumer->is_final_consumer);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_ensure_final_consumer_is_idempotent(): void
    {
        $company = Company::factory()->create();
        $service = app(CustomerService::class);

        $first = $service->ensureFinalConsumer($company);
        $second = $service->ensureFinalConsumer($company);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_customer_with_invoices_cannot_be_physically_deleted(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();
        $customer = Customer::factory()->for($user->company)->create();
        $invoice = new Invoice(['customer_id' => $customer->id, 'issue_date' => now()]);
        $invoice->company_id = $user->company_id;
        $invoice->number = 'FV-0001';
        $invoice->save();

        $response = $this->actingAs($user)->delete(route('customers.destroy', $customer));

        $response->assertRedirect(route('customers.show', $customer));
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_customer_without_invoices_can_be_deleted(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();
        $customer = Customer::factory()->for($user->company)->create();

        $response = $this->actingAs($user)->delete(route('customers.destroy', $customer));

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_a_user_only_sees_customers_from_their_own_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $customerA = Customer::factory()->for($companyA)->create();
        Customer::factory()->for($companyB)->create();

        $userA = User::factory()->for($companyA)->create();

        $this->actingAs($userA)->get(route('customers.show', $customerA))->assertOk();

        $otherCustomer = Customer::withoutGlobalScopes()->where('company_id', $companyB->id)->first();
        $this->actingAs($userA)->get(route('customers.show', $otherCustomer))->assertNotFound();
    }
}
