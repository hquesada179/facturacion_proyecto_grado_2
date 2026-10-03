<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NumberingResolution;
use App\Models\ProductService;
use App\Models\Tax;
use App\Models\User;
use App\Services\Invoices\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Full main-flow E2E: login -> customer -> product -> draft -> items ->
 * calculate -> validate -> issue -> PDF -> QR/public verification ->
 * simulated delivery -> credit note -> traceability -> dashboard/reports.
 *
 * Uses the controlled scenario from the brief: SERV-TEST, quantity 4,
 * unit price 100000, IVA 19% -> base 400000 / IVA 76000 / total 476000.
 * Every assertion checks real persisted values and relationships, not
 * just HTTP status codes.
 */
class EndToEndInvoiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_full_invoice_lifecycle_with_controlled_values(): void
    {
        // --- Fixtures: company, user, numbering resolution, IVA19 tax ---
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->facturador()->create();
        NumberingResolution::factory()->for($company)->create([
            'document_type' => 'invoice',
            'prefix' => 'FV',
            'range_from' => 1,
            'range_to' => 1000,
            'current_consecutive' => 1,
        ]);
        NumberingResolution::factory()->for($company)->creditNote()->create([
            'prefix' => 'NC',
            'range_from' => 1,
            'range_to' => 1000,
            'current_consecutive' => 1,
        ]);
        $tax = Tax::factory()->create(['code' => 'IVA19', 'name' => 'IVA 19%', 'rate' => '19']);

        // --- 1. Login (real HTTP session, not actingAs) ---
        $loginResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $loginResponse->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        // --- 2. Create/select a real customer through the UI ---
        $customerResponse = $this->post('/clientes', [
            'person_type' => 'natural',
            'identification_type' => 'CC',
            'identification_number' => '1000111222',
            'name' => 'Cliente E2E de prueba',
            'email' => 'cliente.e2e@example.com',
            'status' => 'active',
        ]);
        $customerResponse->assertRedirect();
        $customer = Customer::where('identification_number', '1000111222')->firstOrFail();
        $this->assertSame($company->id, $customer->company_id);

        // --- 3. Create/select a real product (SERV-TEST) with IVA 19% ---
        $productResponse = $this->post('/productos-servicios', [
            'sku' => 'SERV-TEST',
            'name' => 'Servicio de prueba E2E',
            'type' => 'service',
            'unit' => 'unidad',
            'price' => '100000',
            'status' => 'active',
            'tax_included' => '0',
            'taxes' => [$tax->id],
        ]);
        $productResponse->assertRedirect();
        $product = ProductService::where('sku', 'SERV-TEST')->firstOrFail();
        $this->assertSame('100000.00', (string) $product->price);

        // --- 4. Create a real, persisted draft invoice ---
        $createResponse = $this->get(route('invoices.create.customer'));
        $invoice = Invoice::latest('id')->firstOrFail();
        $createResponse->assertRedirect(route('invoices.draft.customer', $invoice));
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);

        $this->post(route('invoices.draft.customer.update', $invoice), [
            'customer_id' => $customer->id,
        ])->assertRedirect();

        // --- 5. Add items: quantity 4, unit price 100000, IVA 19% ---
        $this->post(route('invoices.draft.items.store', $invoice), [
            'product_service_id' => $product->id,
            'quantity' => '4',
            'unit_price' => '100000',
            'discount_percent' => '0',
            'tax_ids' => [$tax->id],
        ])->assertRedirect();

        // --- 6. Calculate: real totals must already be base 400000 / IVA 76000 / total 476000 ---
        $invoice->refresh();
        $this->assertSame('400000.00', (string) $invoice->subtotal);
        $this->assertSame('76000.00', (string) $invoice->tax_total);
        $this->assertSame('476000.00', (string) $invoice->total);

        $item = $invoice->items()->with('itemTaxes')->firstOrFail();
        $this->assertSame('4.00', (string) $item->quantity);
        $this->assertSame('400000.00', (string) $item->taxable_base);
        $this->assertSame('76000.00', (string) $item->tax_total);
        $this->assertSame('476000.00', (string) $item->line_total);

        $itemTax = $item->itemTaxes->firstOrFail();
        $this->assertSame('IVA19', $itemTax->code);
        $this->assertSame('400000.00', (string) $itemTax->base);
        $this->assertSame('76000.00', (string) $itemTax->value);

        // --- 7. Validate (local ValidationEngine pass, zero blocking issues) ---
        $this->post(route('invoices.draft.validate', $invoice))->assertRedirect(route('invoices.draft.validation', $invoice));
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::LocallyValidated, $invoice->status);
        $this->assertDatabaseHas('invoice_events', [
            'invoice_id' => $invoice->id,
            'type' => 'local_validation_passed',
        ]);

        // --- 8. Issue (simulated DIAN approval) ---
        $this->post(route('invoices.draft.issue', $invoice))->assertRedirect(route('invoices.show', $invoice));
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame('FV-000001', $invoice->number);
        $this->assertNotNull($invoice->issued_at);
        $this->assertStringStartsWith('SIM-', $invoice->simulated_cufe);
        // Totals must be exactly the same after issuance — emission never recalculates money.
        $this->assertSame('400000.00', (string) $invoice->subtotal);
        $this->assertSame('76000.00', (string) $invoice->tax_total);
        $this->assertSame('476000.00', (string) $invoice->total);

        // --- 9. PDF generation: real file, hash, and exact tax breakdown (no float drift) ---
        $pdfResponse = $this->get(route('invoices.pdf.show', $invoice));
        $pdfResponse->assertOk();
        $pdfResponse->assertHeader('Content-Type', 'application/pdf');
        $invoice->refresh();
        $this->assertNotNull($invoice->pdf_path);
        $this->assertNotNull($invoice->pdf_hash);

        $pdfData = app(InvoicePdfService::class)->viewData($invoice);
        $this->assertSame('400000.00', $pdfData['taxesByCode']['IVA19']['base']);
        $this->assertSame('76000.00', $pdfData['taxesByCode']['IVA19']['value']);
        $this->assertSame('400000.00', $pdfData['taxableBase']);

        // --- 10. QR + public verification page (unauthenticated) ---
        $this->assertNotNull($invoice->verification_token);
        auth()->logout();
        $verifyResponse = $this->get(route('documents.verify', ['token' => $invoice->verification_token]));
        $verifyResponse->assertOk();
        $verifyResponse->assertSee('FV-000001');
        $verifyResponse->assertSee('476.000', false);
        $verifyResponse->assertSee($invoice->simulated_cufe);

        // --- 11. Simulated delivery ---
        $this->actingAs($user);
        $this->post(route('invoices.delivery.send', $invoice))->assertRedirect(route('invoices.show', $invoice));
        $invoice->refresh();
        $this->assertSame('sent_simulated', $invoice->delivery_status);
        $this->assertNotNull($invoice->delivery_simulated_at);

        // --- 12. Credit note: partial credit of quantity 1 out of 4 ---
        $this->post(route('credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'reason_code' => 'PROTO_PARTIAL_RETURN',
            'reason_text' => 'Devolución parcial de prueba E2E',
            'items' => [$item->id => ['quantity' => '1']],
        ])->assertRedirect();

        $creditNote = CreditNote::where('invoice_id', $invoice->id)->latest('id')->firstOrFail();
        $this->post(route('credit-notes.validate', $creditNote))->assertRedirect();
        $this->post(route('credit-notes.issue', $creditNote))->assertRedirect(route('credit-notes.show', $creditNote));

        $creditNote->refresh();
        // 1 of 4 units credited: base 100000, IVA 19% = 19000, total 119000.
        $this->assertSame('100000.00', (string) $creditNote->subtotal);
        $this->assertSame('19000.00', (string) $creditNote->tax_total);
        $this->assertSame('119000.00', (string) $creditNote->total);
        $this->assertNotNull($creditNote->number);
        $this->assertStringStartsWith('SIM-NC-', $creditNote->simulated_cufe);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PartiallyCredited, $invoice->status);
        // The original invoice's own totals are immutable even after a credit note.
        $this->assertSame('400000.00', (string) $invoice->subtotal);
        $this->assertSame('76000.00', (string) $invoice->tax_total);
        $this->assertSame('476000.00', (string) $invoice->total);

        // --- 13. Traceability: every real event is recorded, append-only ---
        $types = $invoice->events()->pluck('type')->all();
        foreach ([
            'customer_selected', 'item_added', 'local_validation_passed',
            'issuance_requested', 'simulated_validation_approved', 'issued',
            'pdf_generated', 'delivery_simulated', 'invoice_partially_credited',
        ] as $expectedType) {
            $this->assertContains($expectedType, $types, "Missing traceability event: {$expectedType}");
        }

        $administrador = User::factory()->for($company)->administrador()->create();
        $traceabilityResponse = $this->actingAs($administrador)->get(route('traceability.index'));
        $traceabilityResponse->assertOk();
        $traceabilityResponse->assertSee('FV-000001');

        // --- 14. Dashboard reflects the real emitted invoice ---
        $dashboardResponse = $this->actingAs($user)->get(route('dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('FV-000001');

        // --- 15. Reports reflect the real documents and totals ---
        $reportsResponse = $this->actingAs($user)->get(route('reports.index'));
        $reportsResponse->assertOk();
        $reportsResponse->assertSee('FV-000001');
        $reportsResponse->assertSee('476.000', false);
    }
}
