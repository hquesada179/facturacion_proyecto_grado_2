<?php

namespace Tests\Feature\Assistant;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NumberingResolution;
use App\Models\ProductService;
use App\Models\Tax;
use App\Models\User;
use App\Services\Assistant\Tools\FindCustomerTool;
use App\Services\Assistant\Tools\FindProductTool;
use App\Services\Assistant\Tools\GetCreditNoteDetailsTool;
use App\Services\Assistant\Tools\GetInvoiceDetailsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantToolsTest extends TestCase
{
    use RefreshDatabase;

    private function facturador(?Company $company = null): User
    {
        return User::factory()->for($company ?? Company::factory())->facturador()->create();
    }

    /** Builds an issued invoice with a 19% IVA line: base $400.000, tax $76.000. */
    private function issuedInvoiceWithIva(User $user): Invoice
    {
        NumberingResolution::factory()->for($user->company)->create([
            'document_type' => 'invoice',
            'prefix' => 'FV',
            'range_from' => 1,
            'range_to' => 100,
            'current_consecutive' => 1,
        ]);

        $invoice = new Invoice(['user_id' => $user->id, 'currency' => 'COP', 'payment_type' => 'contado']);
        $invoice->company_id = $user->company_id;
        $invoice->save();

        $customer = Customer::factory()->for($user->company)->create(['status' => 'active']);
        $product = ProductService::factory()->for($user->company)->create(['price' => '100000.00']);
        $tax = Tax::factory()->create(['code' => 'IVA19', 'name' => 'IVA 19%', 'rate' => '19']);

        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => $customer->id]);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), [
            'product_service_id' => $product->id,
            'quantity' => '4',
            'unit_price' => '100000',
            'discount_percent' => '0',
            'tax_ids' => [$tax->id],
        ]);
        $this->actingAs($user)->post(route('invoices.draft.validate', $invoice));
        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));

        return $invoice->refresh();
    }

    private function createCreditNote(User $user, Invoice $invoice): CreditNote
    {
        $item = $invoice->items()->firstOrFail();

        $this->actingAs($user)->post(route('credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'reason_code' => 'PROTO_PARTIAL_RETURN',
            'reason_text' => 'Ajuste de prueba',
            'items' => [
                $item->id => ['quantity' => '1'],
            ],
        ]);

        return $invoice->creditNotes()->latest('id')->firstOrFail();
    }

    public function test_find_invoice_by_number_only_returns_matches_within_the_users_company(): void
    {
        $user = $this->facturador();
        $invoice = $this->issuedInvoiceWithIva($user);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Busca la factura '.$invoice->number,
            'screen' => 'invoices.index',
        ])->assertOk();

        $this->assertStringContainsString($invoice->number, $response->json('message'));
    }

    public function test_invoice_details_explain_the_real_iva_calculation(): void
    {
        $user = $this->facturador();
        $invoice = $this->issuedInvoiceWithIva($user);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => '¿Cuánto IVA tiene esta factura y cómo se calculó?',
            'screen' => 'invoice.show',
            'resource_type' => 'invoice',
            'resource_id' => $invoice->id,
        ])->assertOk();

        $message = $response->json('message');
        $this->assertStringContainsString('400.000', $message);
        $this->assertStringContainsString('76.000', $message);
        $this->assertSame((float) $invoice->tax_total, 76000.0);
        $this->assertSame((float) $invoice->subtotal, 400000.0);
    }

    public function test_traceability_tool_explains_what_happened_and_respects_the_gate(): void
    {
        $administrador = User::factory()->for(Company::factory())->administrador()->create();
        $invoice = $this->issuedInvoiceWithIva($administrador);

        $response = $this->actingAs($administrador)->postJson(route('assistant.message'), [
            'message' => '¿Qué pasó con este documento?',
            'screen' => 'invoice.show',
            'resource_type' => 'invoice',
            'resource_id' => $invoice->id,
        ])->assertOk();

        $this->assertStringContainsString('Trazabilidad registrada por el sistema', $response->json('message'));
        $this->assertStringContainsString('issued', $response->json('message'));
    }

    public function test_traceability_is_denied_for_roles_without_the_gate(): void
    {
        $contador = User::factory()->for(Company::factory())->contador()->create();
        $invoice = $this->issuedInvoiceWithIva(
            User::factory()->for($contador->company)->facturador()->create()
        );

        $response = $this->actingAs($contador)->postJson(route('assistant.message'), [
            'message' => '¿Qué pasó con este documento?',
            'screen' => 'invoice.show',
            'resource_type' => 'invoice',
            'resource_id' => $invoice->id,
        ])->assertOk();

        $this->assertStringContainsString('no tiene permiso', $response->json('message'));
    }

    public function test_find_customer_tool_returns_only_minimal_safe_fields(): void
    {
        $user = $this->facturador();
        Customer::factory()->for($user->company)->create(['name' => 'Comercializadora Andina SAS', 'status' => 'active']);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Busca el cliente Andina',
            'screen' => 'customers.index',
        ])->assertOk();

        $this->assertStringContainsString('Comercializadora Andina SAS', $response->json('message'));
    }

    public function test_find_product_tool_reports_catalog_data(): void
    {
        $user = $this->facturador();
        $tax = Tax::factory()->create(['code' => 'IVA19', 'rate' => '19']);
        $product = ProductService::factory()->for($user->company)->create(['name' => 'Servicio de soporte', 'price' => '250000']);
        $product->taxes()->attach($tax->id);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Busca el producto soporte',
            'screen' => 'products.index',
        ])->assertOk();

        $this->assertStringContainsString($product->sku, $response->json('message'));
        $this->assertStringContainsString('IVA19', $response->json('message'));
    }

    public function test_credit_note_tool_explains_the_partial_credit(): void
    {
        $user = $this->facturador();
        $invoice = $this->issuedInvoiceWithIva($user);
        $creditNote = $this->createCreditNote($user, $invoice);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => '¿Por qué esta factura está parcialmente acreditada?',
            'screen' => 'credit_note.show',
            'resource_type' => 'credit_note',
            'resource_id' => $creditNote->id,
        ])->assertOk();

        $this->assertStringContainsString($invoice->number, $response->json('message'));
    }

    public function test_invoice_details_tool_rejects_ids_from_another_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = User::factory()->for($companyA)->facturador()->create();
        $userB = User::factory()->for($companyB)->facturador()->create();
        $invoiceB = $this->issuedInvoiceWithIva($userB);

        $result = app(GetInvoiceDetailsTool::class)->handle($userA, $invoiceB->id);

        $this->assertFalse($result['found']);
    }

    public function test_find_customer_tool_never_crosses_company_boundaries(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = User::factory()->for($companyA)->facturador()->create();
        Customer::factory()->for($companyB)->create(['name' => 'Cliente de otra empresa']);

        $result = app(FindCustomerTool::class)->handle($userA, 'Cliente de otra empresa');

        $this->assertSame([], $result['matches']);
    }

    public function test_find_product_tool_never_crosses_company_boundaries(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = User::factory()->for($companyA)->facturador()->create();
        ProductService::factory()->for($companyB)->create(['name' => 'Producto de otra empresa']);

        $result = app(FindProductTool::class)->handle($userA, 'Producto de otra empresa');

        $this->assertSame([], $result['matches']);
    }

    public function test_credit_note_tool_never_crosses_company_boundaries(): void
    {
        $companyB = Company::factory()->create();
        $userB = User::factory()->for($companyB)->facturador()->create();
        $invoiceB = $this->issuedInvoiceWithIva($userB);
        $creditNoteB = $this->createCreditNote($userB, $invoiceB);

        $companyA = Company::factory()->create();
        $userA = User::factory()->for($companyA)->facturador()->create();

        $result = app(GetCreditNoteDetailsTool::class)->handle($userA, $creditNoteB->id);

        $this->assertFalse($result['found']);
    }
}
