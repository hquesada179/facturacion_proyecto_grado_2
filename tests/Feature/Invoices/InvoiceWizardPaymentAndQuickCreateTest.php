<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NumberingResolution;
use App\Models\ProductService;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 2: client/product search convenience, quick-create reuse, the new
 * payment_method field, and the validation screen's "go fix it" links.
 * The wizard's four steps (cliente, productos, resumen, validación) are
 * never replaced here — only enhanced.
 */
class InvoiceWizardPaymentAndQuickCreateTest extends TestCase
{
    use RefreshDatabase;

    private function facturador(): User
    {
        return User::factory()->for(Company::factory())->facturador()->create();
    }

    private function draftInvoiceFor(User $user): Invoice
    {
        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $user->company_id;
        $invoice->save();

        return $invoice;
    }

    public function test_customer_step_renders_searchable_options_with_name_document_and_email(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        Customer::factory()->for($user->company)->create([
            'name' => 'Comercializadora Andina SAS',
            'identification_number' => '900555444',
            'email' => 'contacto@andina.test',
        ]);

        $response = $this->actingAs($user)->get(route('invoices.draft.customer', $invoice));

        $response->assertOk();
        $response->assertSee('id="customer-quick-search"', false);
        $response->assertSee('data-search="comercializadora andina sas 900555444 contacto@andina.test"', false);
    }

    public function test_quick_create_customer_from_the_wizard_still_calculates_the_nit_check_digit(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);

        $this->actingAs($user)->post(route('invoices.draft.customer.quick-create', $invoice), [
            'person_type' => 'juridica',
            'identification_type' => 'NIT',
            'identification_number' => '900373115',
            'name' => 'Proveedor Rápido SAS',
            'phone' => '6015550000',
            'address' => 'Calle 1 # 2-3',
            'status' => 'active',
        ])->assertRedirect(route('invoices.draft.customer', $invoice));

        $this->assertDatabaseHas('customers', [
            'identification_number' => '900373115',
            'dv' => '3',
            'company_id' => $user->company_id,
        ]);

        $invoice->refresh();
        $customer = Customer::where('identification_number', '900373115')->first();
        $this->assertSame($customer->id, $invoice->customer_id);
    }

    public function test_product_step_renders_searchable_options_by_code_name_and_description(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        ProductService::factory()->for($user->company)->create([
            'sku' => 'SRV-900',
            'name' => 'Soporte remoto',
            'description' => 'Atención técnica especializada',
        ]);

        $response = $this->actingAs($user)->get(route('invoices.draft.items', $invoice));

        $response->assertOk();
        $response->assertSee('id="product-quick-search"', false);
        $response->assertSee('data-search="srv-900 soporte remoto atención técnica especializada"', false);
    }

    public function test_quick_create_product_from_the_wizard_is_auto_selected_without_leaving_the_flow(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $tax = Tax::factory()->create(['code' => 'IVA19', 'name' => 'IVA 19%', 'rate' => '19']);

        $response = $this->actingAs($user)->post(route('invoices.draft.items.quick-create-product', $invoice), [
            'sku' => 'SRV-NEW',
            'name' => 'Instalación exprés',
            'type' => 'service',
            'unit' => 'unidad',
            'price' => '250000',
            'status' => 'active',
            'taxes' => [$tax->id],
        ]);

        $product = ProductService::where('sku', 'SRV-NEW')->first();
        $this->assertNotNull($product);
        $this->assertTrue($product->taxes->contains($tax));

        $response->assertRedirect(route('invoices.draft.items', ['invoice' => $invoice, 'selected_product' => $product->id]));

        $itemsPage = $this->actingAs($user)->get($response->headers->get('Location'));
        $itemsPage->assertOk();
        // The new product must come back pre-selected in the dropdown...
        $itemsPage->assertSee('value="'.$product->id.'" data-search="srv-new instalación exprés', false);
        $itemsPage->assertSee('selected>SRV-NEW — Instalación exprés', false);
        // ...and its price must be ready for the autofill JS to use.
        $itemsPage->assertSee('"price":"250000.00"', false);

        $this->assertDatabaseHas('invoice_events', [
            'invoice_id' => $invoice->id,
            'type' => 'product_quick_created',
        ]);
    }

    public function test_quick_create_product_is_blocked_once_invoice_is_issued(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $invoice->status = InvoiceStatus::Issued;
        $invoice->save();

        $this->actingAs($user)->post(route('invoices.draft.items.quick-create-product', $invoice), [
            'sku' => 'SRV-LATE',
            'name' => 'Tarde',
            'type' => 'service',
            'unit' => 'unidad',
            'price' => '1',
            'status' => 'active',
        ])->assertForbidden();

        $this->assertDatabaseMissing('product_services', ['sku' => 'SRV-LATE']);
    }

    public function test_leaving_payment_untouched_never_blocks_emission(): void
    {
        $user = $this->facturador();
        NumberingResolution::factory()->for($user->company)->create(['prefix' => 'FV', 'range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1]);
        $invoice = $this->draftInvoiceFor($user);
        $customer = Customer::factory()->for($user->company)->create(['status' => 'active']);
        $product = ProductService::factory()->for($user->company)->create(['price' => '100000.00']);

        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => $customer->id]);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), ['product_service_id' => $product->id, 'quantity' => '1', 'unit_price' => '100000']);

        // payment_type/payment_method are never touched here — legacy behavior must be preserved.
        $this->actingAs($user)->post(route('invoices.draft.validate', $invoice));
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::LocallyValidated, $invoice->status);

        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));
        $this->assertSame(InvoiceStatus::Issued, $invoice->refresh()->status);
    }

    public function test_choosing_payment_condition_without_a_method_blocks_validation(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $customer = Customer::factory()->for($user->company)->create(['status' => 'active']);
        $product = ProductService::factory()->for($user->company)->create(['price' => '100000.00']);

        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => $customer->id]);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), ['product_service_id' => $product->id, 'quantity' => '1', 'unit_price' => '100000']);
        $this->actingAs($user)->post(route('invoices.draft.summary.update', $invoice), ['payment_type' => 'contado']);

        $this->actingAs($user)->post(route('invoices.draft.validate', $invoice));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Draft, $invoice->status, 'Must stay in draft: payment_method is still "Sin definir".');

        $response = $this->actingAs($user)->get(route('invoices.draft.validation', $invoice));
        $response->assertOk();
        $response->assertSee('PRO-PAYMENT-003');
        $response->assertSee('Corregir datos de pago');
    }

    public function test_choosing_payment_condition_and_method_together_allows_emission(): void
    {
        $user = $this->facturador();
        NumberingResolution::factory()->for($user->company)->create(['prefix' => 'FV', 'range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1]);
        $invoice = $this->draftInvoiceFor($user);
        $customer = Customer::factory()->for($user->company)->create(['status' => 'active']);
        $product = ProductService::factory()->for($user->company)->create(['price' => '100000.00']);

        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => $customer->id]);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), ['product_service_id' => $product->id, 'quantity' => '1', 'unit_price' => '100000']);
        $this->actingAs($user)->post(route('invoices.draft.summary.update', $invoice), [
            'payment_type' => 'credito',
            'payment_method' => 'transferencia',
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->actingAs($user)->post(route('invoices.draft.validate', $invoice));
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::LocallyValidated, $invoice->status);
        $this->assertSame('credito', $invoice->payment_type);
        $this->assertSame('transferencia', $invoice->payment_method);

        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));
        $this->assertSame(InvoiceStatus::Issued, $invoice->refresh()->status);
    }

    public function test_credit_without_a_method_reports_both_payment_codes(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $customer = Customer::factory()->for($user->company)->create(['status' => 'active']);
        $product = ProductService::factory()->for($user->company)->create(['price' => '100000.00']);

        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => $customer->id]);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), ['product_service_id' => $product->id, 'quantity' => '1', 'unit_price' => '100000']);
        $this->actingAs($user)->post(route('invoices.draft.summary.update', $invoice), ['payment_type' => 'credito']);

        $response = $this->actingAs($user)->get(route('invoices.draft.validation', $invoice));

        $response->assertOk();
        // Missing due_date (PRO-PAYMENT-001) AND missing payment_method (PRO-PAYMENT-003) — both existing codes preserved.
        $response->assertSee('PRO-PAYMENT-001');
        $response->assertSee('PRO-PAYMENT-003');
    }

    public function test_validation_page_links_blocking_customer_issue_to_the_customer_step(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $product = ProductService::factory()->for($user->company)->create(['price' => '100000.00']);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), ['product_service_id' => $product->id, 'quantity' => '1', 'unit_price' => '100000']);

        $response = $this->actingAs($user)->get(route('invoices.draft.validation', $invoice));

        $response->assertOk();
        $response->assertSee('Ir a cliente');
        $response->assertSee(route('invoices.draft.customer', $invoice), false);
    }

    public function test_validation_page_links_blocking_item_issue_to_the_products_step(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $customer = Customer::factory()->for($user->company)->create(['status' => 'active']);
        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => $customer->id]);

        $response = $this->actingAs($user)->get(route('invoices.draft.validation', $invoice));

        $response->assertOk();
        $response->assertSee('Ir a productos');
        $response->assertSee(route('invoices.draft.items', $invoice), false);
    }
}
