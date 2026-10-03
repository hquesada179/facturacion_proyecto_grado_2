<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Mail\SimulatedInvoiceDelivery;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\NumberingResolution;
use App\Models\ProductService;
use App\Models\Tax;
use App\Models\User;
use App\Services\Invoices\InvoicePdfService;
use App\Services\Invoices\InvoiceQrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoicePdfDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function facturador(?Company $company = null): User
    {
        return User::factory()->for($company ?? Company::factory())->facturador()->create();
    }

    private function administrador(?Company $company = null): User
    {
        return User::factory()->for($company ?? Company::factory())->administrador()->create();
    }

    private function draftInvoiceFor(User $user): Invoice
    {
        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $user->company_id;
        $invoice->save();

        return $invoice;
    }

    /**
     * Builds and issues through the real wizard so totals, snapshots,
     * numbering and traceability use the production path.
     *
     * @return array{invoice: Invoice, customer: Customer, product: ProductService}
     */
    private function issuedInvoiceFor(User $user, array $customerOverrides = [], array $productOverrides = []): array
    {
        NumberingResolution::factory()->for($user->company)->create([
            'prefix' => 'FV',
            'range_from' => 1,
            'range_to' => 100,
            'current_consecutive' => 1,
        ]);

        $invoice = $this->draftInvoiceFor($user);
        $customer = Customer::factory()->for($user->company)->create(array_merge([
            'name' => 'Cliente Snapshot SAS',
            'email' => 'cliente.snapshot@example.test',
            'identification_type' => 'CC',
            'identification_number' => '1001234567',
            'dv' => null,
            'status' => 'active',
        ], $customerOverrides));
        $product = ProductService::factory()->for($user->company)->create(array_merge([
            'sku' => 'SRV-001',
            'name' => 'Servicio profesional snapshot',
            'price' => '100000.00',
        ], $productOverrides));
        $tax = Tax::factory()->create([
            'code' => 'IVA19',
            'name' => 'IVA 19%',
            'rate' => '19',
        ]);

        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), [
            'customer_id' => $customer->id,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), [
            'product_service_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '100000',
            'discount_percent' => '0',
            'tax_ids' => [$tax->id],
        ])->assertRedirect();

        $this->actingAs($user)->post(route('invoices.draft.validate', $invoice))->assertRedirect();
        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice))->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);

        return ['invoice' => $invoice, 'customer' => $customer, 'product' => $product];
    }

    public function test_pdf_is_generated_for_issued_invoice_and_stored_privately_with_traceability(): void
    {
        Storage::fake('local');
        $user = $this->facturador();
        $invoice = $this->issuedInvoiceFor($user)['invoice'];

        $response = $this->actingAs($user)->get(route('invoices.pdf.show', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->content());

        $invoice->refresh();
        $this->assertNotNull($invoice->pdf_path);
        $this->assertStringStartsWith('invoices/'.$invoice->company_id.'/', $invoice->pdf_path);
        Storage::disk('local')->assertExists($invoice->pdf_path);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($invoice->pdf_path)), $invoice->pdf_hash);
        $this->assertNotNull($invoice->pdf_generated_at);

        $this->assertDatabaseHas('invoice_events', [
            'invoice_id' => $invoice->id,
            'type' => 'pdf_generated',
        ]);
    }

    public function test_draft_invoice_cannot_generate_definitive_pdf(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);

        $this->actingAs($user)->get(route('invoices.pdf.show', $invoice))->assertForbidden();

        $this->assertNull($invoice->refresh()->pdf_path);
    }

    public function test_pdf_view_contains_required_prototype_content_and_snapshots(): void
    {
        $user = $this->facturador();
        $context = $this->issuedInvoiceFor($user);
        $invoice = $context['invoice'];

        $html = view('invoices.pdf', app(InvoicePdfService::class)->viewData($invoice))->render();

        $this->assertStringContainsString('DOCUMENTO DE PRUEBA – SIN VALIDEZ TRIBUTARIA', $html);
        $this->assertStringContainsString('Validación DIAN simulada', $html);
        $this->assertStringContainsString('CUFE SIMULADO', $html);
        $this->assertStringContainsString('SIM-', $html);
        $this->assertStringContainsString('Cliente Snapshot SAS', $html);
        $this->assertStringContainsString('SRV-001', $html);
        $this->assertStringContainsString('IVA19', $html);
        $this->assertStringContainsString('Total factura', $html);
    }

    public function test_qr_is_generated_for_internal_verification_and_never_points_to_dian(): void
    {
        $user = $this->facturador();
        $invoice = $this->issuedInvoiceFor($user)['invoice'];
        $service = app(InvoicePdfService::class);

        $verificationUrl = $service->verificationUrl($invoice);
        $qrSvg = app(InvoiceQrCodeService::class)->svg($verificationUrl);

        $this->assertStringContainsString('/verificar-documento/', $verificationUrl);
        $this->assertStringNotContainsString('dian', strtolower($verificationUrl));
        $this->assertStringContainsString('<svg', $qrSvg);
        $this->assertNotNull($invoice->refresh()->verification_token);
    }

    public function test_public_verification_page_accepts_valid_token_and_hides_customer_data(): void
    {
        $user = $this->facturador();
        $context = $this->issuedInvoiceFor($user);
        $invoice = app(InvoicePdfService::class)->ensureVerificationToken($context['invoice']);

        $response = $this->get(route('documents.verify', $invoice->verification_token));

        $response->assertOk()
            ->assertSee('Verificación de documento de prueba')
            ->assertSee($invoice->number)
            ->assertSee($invoice->issuer_snapshot['legal_name'] ?? $invoice->issuer_snapshot['name'])
            ->assertSee($invoice->simulated_cufe)
            ->assertSee('Documento de prueba')
            ->assertDontSee($context['customer']->name)
            ->assertDontSee($context['customer']->email);

        $event = InvoiceEvent::where('invoice_id', $invoice->id)
            ->where('type', 'verification_page_viewed')
            ->first();

        $this->assertNotNull($event);
        $this->assertNull($event->user_id);
    }

    public function test_public_verification_page_rejects_invalid_token(): void
    {
        $this->get('/verificar-documento/token-inexistente')->assertNotFound();
    }

    public function test_pdf_download_requires_auth_and_respects_company_isolation(): void
    {
        Storage::fake('local');
        $user = $this->facturador();
        $invoice = $this->issuedInvoiceFor($user)['invoice'];
        $otherUser = $this->facturador();

        auth()->logout();
        $this->flushSession();

        $this->get(route('invoices.pdf.download', $invoice))->assertRedirect(route('login'));

        $this->actingAs($otherUser)->get(route('invoices.pdf.download', $invoice))->assertNotFound();

        $this->actingAs($user)->get(route('invoices.pdf.download', $invoice))->assertOk();

        $this->assertDatabaseHas('invoice_events', [
            'invoice_id' => $invoice->id,
            'type' => 'pdf_downloaded',
        ]);
    }

    public function test_simulated_delivery_uses_log_mailer_and_records_traceability(): void
    {
        Storage::fake('local');
        Mail::fake();
        $user = $this->facturador();
        $invoice = $this->issuedInvoiceFor($user)['invoice'];

        $this->actingAs($user)->post(route('invoices.delivery.send', $invoice))
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('status');

        $invoice->refresh();
        $this->assertSame('sent_simulated', $invoice->delivery_status);
        $this->assertNotNull($invoice->delivery_simulated_at);
        $this->assertNotNull($invoice->pdf_path);
        Mail::assertSent(SimulatedInvoiceDelivery::class);

        $events = InvoiceEvent::where('invoice_id', $invoice->id)
            ->where('type', 'delivery_simulated')
            ->get();

        $this->assertTrue($events->contains(fn (InvoiceEvent $event): bool => ($event->metadata['status'] ?? null) === 'pending_simulated'));
        $this->assertTrue($events->contains(fn (InvoiceEvent $event): bool => ($event->metadata['status'] ?? null) === 'sent_simulated'));
        $this->assertTrue($events->contains(fn (InvoiceEvent $event): bool => ($event->metadata['mailer'] ?? null) === 'log'));
    }

    public function test_generated_document_keeps_customer_snapshot_after_customer_changes(): void
    {
        Storage::fake('local');
        $user = $this->administrador();
        $context = $this->issuedInvoiceFor($user);
        $invoice = app(InvoicePdfService::class)->ensureGenerated($context['invoice']);
        $initialHash = $invoice->pdf_hash;

        $context['customer']->update(['name' => 'Cliente vivo modificado']);

        $html = view('invoices.pdf', app(InvoicePdfService::class)->viewData($invoice->refresh()))->render();
        $this->assertStringContainsString('Cliente Snapshot SAS', $html);
        $this->assertStringNotContainsString('Cliente vivo modificado', $html);

        $invoice = app(InvoicePdfService::class)->ensureGenerated($invoice->refresh());
        $this->assertSame($initialHash, $invoice->pdf_hash);
    }
}
