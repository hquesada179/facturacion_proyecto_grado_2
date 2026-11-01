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

    /**
     * Regression test: customers.show previously hardcoded an empty rows
     * array for "Facturas del cliente", so a customer with a real invoice
     * still showed the empty-state message.
     */
    public function test_customer_detail_shows_its_invoices_and_credit_notes(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();
        $customer = Customer::factory()->for($user->company)->create(['name' => 'Cliente Demo Emisión']);

        $invoice = new Invoice(['customer_id' => $customer->id]);
        $invoice->company_id = $user->company_id;
        $invoice->number = 'FV-000001';
        $invoice->status = \App\Enums\InvoiceStatus::Issued;
        $invoice->issued_at = now();
        $invoice->total = 119000;
        $invoice->save();

        $creditNote = new \App\Models\CreditNote(['invoice_id' => $invoice->id, 'reason' => 'Anulación total (prototipo)', 'reason_code' => 'PROTO_TOTAL_VOID']);
        $creditNote->company_id = $user->company_id;
        $creditNote->number = 'NC-000001';
        $creditNote->status = \App\Enums\CreditNoteStatus::Issued;
        $creditNote->total = 19000;
        $creditNote->save();

        $response = $this->actingAs($user)->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertSee('FV-000001');
        $response->assertSee('NC-000001');
        $response->assertDontSee('Este cliente todavía no tiene facturas.');
    }

    /**
     * Regression test: "Consumidor Final" had no special protection —
     * an admin could edit, inactivate, or permanently delete it like
     * any regular customer, silently corrupting or removing the
     * company's only fixed placeholder.
     */
    public function test_final_consumer_cannot_be_edited(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();
        $finalConsumer = app(CustomerService::class)->ensureFinalConsumer($user->company);

        $this->actingAs($user)->get(route('customers.edit', $finalConsumer))->assertForbidden();
        $this->actingAs($user)->put(route('customers.update', $finalConsumer), $this->validPayload([
            'identification_number' => '1111111111',
        ]))->assertForbidden();

        $this->assertSame('0000000000', $finalConsumer->refresh()->identification_number);
    }

    public function test_final_consumer_cannot_be_inactivated(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();
        $finalConsumer = app(CustomerService::class)->ensureFinalConsumer($user->company);

        $this->actingAs($user)->patch(route('customers.toggle-status', $finalConsumer))->assertForbidden();

        $this->assertSame('active', $finalConsumer->refresh()->status);
    }

    public function test_final_consumer_cannot_be_deleted(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();
        $finalConsumer = app(CustomerService::class)->ensureFinalConsumer($user->company);

        $this->actingAs($user)->delete(route('customers.destroy', $finalConsumer))->assertForbidden();

        $this->assertDatabaseHas('customers', ['id' => $finalConsumer->id]);
    }

    public function test_final_consumer_edit_and_delete_actions_are_hidden_from_its_detail_page(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();
        $finalConsumer = app(CustomerService::class)->ensureFinalConsumer($user->company);

        $response = $this->actingAs($user)->get(route('customers.show', $finalConsumer));

        $response->assertOk();
        $response->assertDontSee('Editar');
        $response->assertDontSee('route(\'customers.destroy\'', false);
    }

    /**
     * Regression test: duplicate identification numbers with purely
     * cosmetic formatting differences (dots, dashes, spaces) slipped
     * past the uniqueness check because nothing normalized the value
     * before comparing or storing it.
     */
    public function test_identification_number_with_different_formatting_is_treated_as_a_duplicate(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();
        Customer::factory()->for($user->company)->create(['identification_number' => '900373115']);

        $response = $this->actingAs($user)->post(route('customers.store'), $this->validPayload([
            'person_type' => 'juridica',
            'identification_type' => 'NIT',
            'identification_number' => '900.373.115',
            'phone' => '6015550000',
            'address' => 'Calle 1 # 2-3',
        ]));

        $response->assertSessionHasErrors('identification_number');
    }

    public function test_identification_number_is_stored_normalized(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $this->actingAs($user)->post(route('customers.store'), $this->validPayload([
            'person_type' => 'juridica',
            'identification_type' => 'NIT',
            'identification_number' => '900.373.115',
            'phone' => '6015550000',
            'address' => 'Calle 1 # 2-3',
        ]));

        $this->assertDatabaseHas('customers', ['identification_number' => '900373115']);
        $this->assertDatabaseMissing('customers', ['identification_number' => '900.373.115']);
    }

    public function test_cc_identification_number_must_be_purely_numeric(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $response = $this->actingAs($user)->post(route('customers.store'), $this->validPayload([
            'identification_type' => 'CC',
            'identification_number' => '10AB304050',
        ]));

        $response->assertSessionHasErrors('identification_number');
    }

    public function test_passport_identification_number_may_contain_letters(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();

        $response = $this->actingAs($user)->post(route('customers.store'), $this->validPayload([
            'identification_type' => 'PAS',
            'identification_number' => 'AB1234567',
        ]));

        $response->assertSessionDoesntHaveErrors('identification_number');
        $this->assertDatabaseHas('customers', ['identification_number' => 'AB1234567']);
    }

    /**
     * Regression test: editing a customer duplicated the DV-calculation
     * logic instead of reusing CustomerService — functionally identical
     * today, but a maintenance trap. Covering the behavior here so a
     * future refactor can't silently drop it.
     */
    public function test_updating_a_customer_recalculates_the_check_digit_via_the_shared_service(): void
    {
        $user = User::factory()->for(Company::factory())->facturador()->create();
        $customer = Customer::factory()->for($user->company)->create(['identification_type' => 'CC']);

        $this->actingAs($user)->put(route('customers.update', $customer), $this->validPayload([
            'person_type' => 'juridica',
            'identification_type' => 'NIT',
            'identification_number' => '900373115',
            'phone' => '6015550000',
            'address' => 'Calle 1 # 2-3',
        ]));

        $this->assertSame('3', $customer->refresh()->dv);
    }

    /**
     * Regression test: the customer detail page only showed 4 of the
     * ~13 captured fields — person_type, phone, address, department,
     * country, responsibility and tributo were invisible after saving.
     */
    public function test_customer_detail_shows_all_captured_fields(): void
    {
        $user = User::factory()->for(Company::factory())->administrador()->create();
        $tax = \App\Models\Tax::factory()->create(['company_id' => null, 'name' => 'IVA 19% test']);
        $customer = Customer::factory()->for($user->company)->create([
            'person_type' => 'juridica',
            'phone' => '6011234567',
            'address' => 'Calle 123',
            'department' => 'Antioquia',
            'country' => 'Colombia',
            'tax_responsibility' => 'Responsable de IVA',
            'tax_id' => $tax->id,
        ]);

        $response = $this->actingAs($user)->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertSee('Persona jurídica');
        $response->assertSee('6011234567');
        $response->assertSee('Calle 123');
        $response->assertSee('Antioquia');
        $response->assertSee('Responsable de IVA');
        $response->assertSee('IVA 19% test');
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
