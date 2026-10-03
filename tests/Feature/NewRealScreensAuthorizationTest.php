<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NumberingResolution;
use App\Models\ProductService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewRealScreensAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function facturador(?Company $company = null): User
    {
        return User::factory()->for($company ?? Company::factory())->facturador()->create();
    }

    private function issuedInvoiceFor(User $user, string $number = 'FV'): Invoice
    {
        NumberingResolution::factory()->for($user->company)->create(['prefix' => $number, 'range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1]);

        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $user->company_id;
        $invoice->save();

        $customer = Customer::factory()->for($user->company)->create(['status' => 'active']);
        $product = ProductService::factory()->for($user->company)->create(['price' => '100000.00']);

        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => $customer->id]);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), ['product_service_id' => $product->id, 'quantity' => '1', 'unit_price' => '100000']);
        $this->actingAs($user)->post(route('invoices.draft.validate', $invoice));
        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));

        return $invoice->refresh();
    }

    public function test_the_real_invoice_list_never_shows_another_companys_documents(): void
    {
        $userA = $this->facturador();
        $userB = $this->facturador();
        $invoiceA = $this->issuedInvoiceFor($userA, 'FVA');
        $invoiceB = $this->issuedInvoiceFor($userB, 'FVB');
        session()->flush(); // drop the stale "issued" flash from the fixture setup above

        $response = $this->actingAs($userA)->get(route('invoices.index'));

        $response->assertOk();
        $response->assertSee($invoiceA->number);
        $response->assertDontSee($invoiceB->number);
    }

    public function test_the_real_traceability_page_never_shows_another_companys_events(): void
    {
        $adminA = User::factory()->for(Company::factory())->administrador()->create();
        $userB = $this->facturador();
        $invoiceA = $this->issuedInvoiceFor(User::factory()->for($adminA->company)->facturador()->create(), 'FVA');
        $invoiceB = $this->issuedInvoiceFor($userB, 'FVB');
        session()->flush(); // drop the stale "issued" flash from the fixture setup above

        $response = $this->actingAs($adminA)->get(route('traceability.index'));

        $response->assertOk();
        $response->assertSee($invoiceA->number);
        $response->assertDontSee($invoiceB->number);
    }

    public function test_traceability_page_is_forbidden_for_roles_without_the_gate(): void
    {
        $facturador = $this->facturador();
        $contador = User::factory()->for($facturador->company)->contador()->create();

        $this->actingAs($facturador)->get(route('traceability.index'))->assertForbidden();
        $this->actingAs($contador)->get(route('traceability.index'))->assertForbidden();
    }

    public function test_traceability_page_is_allowed_for_administrador_and_auditor(): void
    {
        $company = Company::factory()->create();
        $administrador = User::factory()->for($company)->administrador()->create();
        $auditor = User::factory()->for($company)->auditor()->create();

        $this->actingAs($administrador)->get(route('traceability.index'))->assertOk();
        $this->actingAs($auditor)->get(route('traceability.index'))->assertOk();
    }

    public function test_the_profile_page_always_shows_the_authenticated_users_own_data(): void
    {
        $company = Company::factory()->create();
        $userA = User::factory()->for($company)->facturador()->create(['name' => 'Facturador Uno']);
        $userB = User::factory()->for($company)->contador()->create(['name' => 'Contador Dos']);

        $responseA = $this->actingAs($userA)->get(route('profile.show'));
        $responseA->assertOk();
        $responseA->assertSee('Facturador Uno');
        $responseA->assertDontSee('Contador Dos');

        $responseB = $this->actingAs($userB)->get(route('profile.show'));
        $responseB->assertOk();
        $responseB->assertSee('Contador Dos');
        $responseB->assertDontSee('Facturador Uno');
    }

    public function test_all_four_roles_can_reach_the_real_invoice_list(): void
    {
        foreach (['administrador', 'facturador', 'contador', 'auditor'] as $role) {
            $user = User::factory()->for(Company::factory())->{$role}()->create();

            $this->actingAs($user)->get(route('invoices.index'))->assertOk();
        }
    }
}
