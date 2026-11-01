<?php

namespace Tests\Feature\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NumberingResolution;
use App\Models\ProductService;
use App\Models\Tax;
use App\Models\User;
use App\Services\CreditNotes\CreditNotePdfService;
use App\Services\Invoices\InvoiceQrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fase 5 fix: credit notes had no verification_token column at all and
 * CreditNotePdfService::viewData() never produced a QR — every invoice
 * had one, but a credit note's PDF had no verification mechanism
 * whatsoever. These tests mirror InvoicePdfDeliveryTest's /
 * PublicVerificationAndPdfSecurityTest's invoice coverage for the
 * credit note's own, independent token and QR.
 */
class CreditNotePdfAndVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function facturador(?Company $company = null): User
    {
        return User::factory()->for($company ?? Company::factory())->facturador()->create();
    }

    private function issuedCreditNoteFor(User $user): array
    {
        NumberingResolution::factory()->for($user->company)->create(['prefix' => 'FV', 'range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1]);
        NumberingResolution::factory()->for($user->company)->creditNote()->create(['prefix' => 'NC', 'range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1]);

        $invoice = new Invoice(['user_id' => $user->id, 'currency' => 'COP']);
        $invoice->company_id = $user->company_id;
        $invoice->save();

        $customer = Customer::factory()->for($user->company)->create(['status' => 'active', 'name' => 'Cliente NC Snapshot']);
        $product = ProductService::factory()->for($user->company)->create(['sku' => 'SRV-NC', 'price' => '100000.00']);
        $tax = Tax::factory()->create(['code' => 'IVA19', 'name' => 'IVA 19%', 'rate' => '19']);

        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => $customer->id]);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), [
            'product_service_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '100000',
            'tax_ids' => [$tax->id],
        ]);
        $this->actingAs($user)->post(route('invoices.draft.validate', $invoice));
        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));
        $invoice->refresh();

        $item = $invoice->items()->firstOrFail();
        $this->actingAs($user)->post(route('credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'reason_code' => 'PROTO_PARTIAL_RETURN',
            'reason_text' => 'Justificación de prueba',
            'items' => [$item->id => ['quantity' => '1']],
        ]);
        $creditNote = CreditNote::latest('id')->firstOrFail();

        $this->actingAs($user)->post(route('credit-notes.validate', $creditNote));
        $this->actingAs($user)->post(route('credit-notes.issue', $creditNote));
        $creditNote->refresh();
        $this->assertSame(CreditNoteStatus::Issued, $creditNote->status);

        return ['creditNote' => $creditNote, 'invoice' => $invoice, 'customer' => $customer];
    }

    public function test_credit_note_pdf_contains_its_own_qr_and_verification_url(): void
    {
        $user = $this->facturador();
        $creditNote = $this->issuedCreditNoteFor($user)['creditNote'];

        $html = view('credit-notes.pdf', app(CreditNotePdfService::class)->viewData($creditNote))->render();

        $this->assertStringContainsString('/verificar-documento/', $html);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        $this->assertStringContainsString('QR interno del prototipo', $html);
        $this->assertNotNull($creditNote->refresh()->verification_token);
    }

    public function test_credit_note_verification_token_is_independent_from_its_invoice_token(): void
    {
        $user = $this->facturador();
        $context = $this->issuedCreditNoteFor($user);
        $creditNote = app(CreditNotePdfService::class)->ensureVerificationToken($context['creditNote']);
        $invoice = app(\App\Services\Invoices\InvoicePdfService::class)->ensureVerificationToken($context['invoice']);

        $this->assertNotNull($invoice->verification_token);
        $this->assertNotNull($creditNote->verification_token);
        $this->assertNotSame($invoice->verification_token, $creditNote->verification_token);
    }

    public function test_public_verification_page_resolves_a_credit_note_token_and_hides_customer_data(): void
    {
        $user = $this->facturador();
        $context = $this->issuedCreditNoteFor($user);
        $creditNote = app(CreditNotePdfService::class)->ensureVerificationToken($context['creditNote']);

        $response = $this->get(route('documents.verify', ['token' => $creditNote->verification_token]));

        $response->assertOk();
        $response->assertSee('Verificación de documento de prueba');
        $response->assertSee($creditNote->number);
        $response->assertSee($context['invoice']->number);
        $response->assertSee($creditNote->simulated_cufe);
        $response->assertDontSee('Cliente NC Snapshot');
    }

    public function test_pdf_download_is_blocked_for_a_different_company(): void
    {
        $userA = $this->facturador();
        $userB = $this->facturador();
        $creditNoteB = $this->issuedCreditNoteFor($userB)['creditNote'];

        $this->actingAs($userA)->get(route('credit-notes.pdf.show', $creditNoteB))->assertNotFound();
        $this->actingAs($userA)->get(route('credit-notes.pdf.download', $creditNoteB))->assertNotFound();
    }

    public function test_pdf_cannot_be_generated_before_the_credit_note_is_issued(): void
    {
        $user = $this->facturador();
        NumberingResolution::factory()->for($user->company)->create(['prefix' => 'FV', 'range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1]);

        $invoice = new Invoice(['user_id' => $user->id, 'currency' => 'COP']);
        $invoice->company_id = $user->company_id;
        $invoice->save();
        $customer = Customer::factory()->for($user->company)->create(['status' => 'active']);
        $product = ProductService::factory()->for($user->company)->create(['price' => '100000.00']);

        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => $customer->id]);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), [
            'product_service_id' => $product->id,
            'quantity' => '2',
            'unit_price' => '100000',
        ]);
        $this->actingAs($user)->post(route('invoices.draft.validate', $invoice));
        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));
        $invoice->refresh();
        $item = $invoice->items()->firstOrFail();

        $this->actingAs($user)->post(route('credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'reason_code' => 'PROTO_VALUE_CORRECTION',
            'reason_text' => 'Aún en borrador',
            'items' => [$item->id => ['quantity' => '1']],
        ]);
        $draftNote = CreditNote::latest('id')->firstOrFail();
        $this->assertSame(CreditNoteStatus::Draft, $draftNote->status);

        $this->actingAs($user)->get(route('credit-notes.pdf.show', $draftNote))->assertForbidden();
    }

    public function test_pdf_generation_is_logged_and_stored_privately(): void
    {
        Storage::fake('local');
        $user = $this->facturador();
        $creditNote = $this->issuedCreditNoteFor($user)['creditNote'];

        $response = $this->actingAs($user)->get(route('credit-notes.pdf.show', $creditNote));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $creditNote->refresh();
        Storage::disk('local')->assertExists($creditNote->pdf_path);
        $this->assertDatabaseHas('invoice_events', [
            'invoice_id' => $creditNote->invoice_id,
            'type' => 'credit_note_pdf_generated',
        ]);
    }

    /**
     * Regression test for the exact bug reported from manual QA: a
     * credit note whose PDF was already cached (pdf_path set) from
     * before the QR/verification_token feature existed must still end
     * up with a verification_token the moment ensureGenerated() runs
     * again — proving the lazy-token assignment itself isn't blocked by
     * the cache check. (The cached *file* staying stale until it's
     * explicitly regenerated is the same intentional immutability
     * invariant invoices already rely on — see
     * test_cached_pdf_is_not_silently_regenerated below — so this test
     * only asserts the token side, which is the part that must never be
     * skipped.)
     */
    public function test_ensure_generated_assigns_a_token_even_when_a_pdf_was_already_cached(): void
    {
        Storage::fake('local');
        $user = $this->facturador();
        $creditNote = $this->issuedCreditNoteFor($user)['creditNote'];

        // Simulate a pre-existing cached PDF from before this feature
        // existed: pdf_path set, verification_token still null.
        Storage::disk('local')->put('credit-notes/fake-pre-existing.pdf', '%PDF-fake-old-content');
        $creditNote->forceFill([
            'pdf_path' => 'credit-notes/fake-pre-existing.pdf',
            'pdf_hash' => hash('sha256', '%PDF-fake-old-content'),
            'pdf_generated_at' => now()->subDays(10),
            'verification_token' => null,
        ])->save();

        $result = app(CreditNotePdfService::class)->ensureGenerated($creditNote->refresh());

        $this->assertNotNull($result->verification_token);
        // The stale cached file itself is untouched by design (same
        // immutability rule invoices already have).
        $this->assertSame('credit-notes/fake-pre-existing.pdf', $result->pdf_path);
    }

    /**
     * Documents the pre-existing, intentional immutability behavior
     * (shared with InvoicePdfService): once a PDF is cached, calling
     * ensureGenerated() again never silently rewrites it — even if the
     * template changed since. Forcing a true regeneration (clearing the
     * cached fields, exactly what the manual fix for NC-000001 did)
     * must then produce a fresh file that does contain the QR.
     */
    public function test_forcing_regeneration_of_a_stale_cached_pdf_produces_a_file_with_the_qr(): void
    {
        Storage::fake('local');
        $user = $this->facturador();
        $creditNote = $this->issuedCreditNoteFor($user)['creditNote'];
        // Establish a real token first, as if the token had already
        // been assigned on an earlier (pre-QR-template) PDF generation.
        $originalToken = app(CreditNotePdfService::class)->ensureVerificationToken($creditNote)->verification_token;
        $this->assertNotNull($originalToken);

        $staleContent = '%PDF-fake-old-content-without-qr';
        Storage::disk('local')->put('credit-notes/fake-pre-existing.pdf', $staleContent);
        $creditNote->forceFill([
            'pdf_path' => 'credit-notes/fake-pre-existing.pdf',
            'pdf_hash' => hash('sha256', $staleContent),
            'pdf_generated_at' => now()->subDays(10),
        ])->save();

        // Cache still short-circuits: the stale file is returned as-is.
        $cached = app(CreditNotePdfService::class)->ensureGenerated($creditNote->refresh());
        $this->assertSame($staleContent, Storage::disk('local')->get($cached->pdf_path));

        // Force a real regeneration, exactly like the manual fix applied to NC-000001.
        $creditNote->forceFill(['pdf_path' => null, 'pdf_hash' => null, 'pdf_generated_at' => null])->save();
        $regenerated = app(CreditNotePdfService::class)->ensureGenerated($creditNote->refresh());

        $freshContent = Storage::disk('local')->get($regenerated->pdf_path);
        $this->assertStringStartsWith('%PDF', $freshContent);
        $this->assertNotSame($staleContent, $freshContent);
        // The verification token is preserved, not rotated, by a regeneration.
        $this->assertSame($originalToken, $regenerated->verification_token);

        $html = view('credit-notes.pdf', app(CreditNotePdfService::class)->viewData($regenerated))->render();
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
    }

    public function test_an_unknown_credit_note_verification_token_returns_404(): void
    {
        $response = $this->get('/verificar-documento/'.str_repeat('z', 64));

        $response->assertNotFound();
    }

    /**
     * The public route resolves strictly by the random token column,
     * never by the credit note's numeric id — so a sequential/guessable
     * id can never be used to reach another company's document through
     * this route.
     */
    public function test_credit_note_id_cannot_be_used_as_a_verification_token(): void
    {
        $user = $this->facturador();
        $creditNote = $this->issuedCreditNoteFor($user)['creditNote'];

        $response = $this->get('/verificar-documento/'.$creditNote->id);

        $response->assertNotFound();
    }

    public function test_qr_payload_never_mentions_dian(): void
    {
        $user = $this->facturador();
        $creditNote = $this->issuedCreditNoteFor($user)['creditNote'];
        $service = app(CreditNotePdfService::class);

        $verificationUrl = $service->verificationUrl($creditNote);
        $qrSvg = app(InvoiceQrCodeService::class)->svg($verificationUrl);

        $this->assertStringNotContainsString('dian', strtolower($verificationUrl));
        $this->assertStringContainsString('<svg', $qrSvg);
    }
}
