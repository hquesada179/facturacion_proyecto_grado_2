<?php

namespace Tests\Feature\Documents;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NumberingResolution;
use App\Models\ProductService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicVerificationAndPdfSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function facturador(?Company $company = null): User
    {
        return User::factory()->for($company ?? Company::factory())->facturador()->create();
    }

    private function issuedInvoiceFor(User $user): Invoice
    {
        NumberingResolution::factory()->for($user->company)->create(['prefix' => 'FV', 'range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1]);

        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $user->company_id;
        $invoice->save();

        $customer = Customer::factory()->for($user->company)->create([
            'status' => 'active',
            'email' => 'cliente-secreto@example.com',
            'phone' => '3000000000',
            'address' => 'Calle secreta 123',
        ]);
        $product = ProductService::factory()->for($user->company)->create(['price' => '100000.00']);

        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => $customer->id]);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), [
            'product_service_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '100000',
        ]);
        $this->actingAs($user)->post(route('invoices.draft.validate', $invoice));
        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));

        return $invoice->refresh();
    }

    public function test_verification_token_has_high_entropy_and_is_not_sequential(): void
    {
        $user = $this->facturador();
        $invoiceA = $this->issuedInvoiceFor($user);
        $invoiceB = $this->issuedInvoiceFor($user);

        $this->actingAs($user)->get(route('invoices.pdf.show', $invoiceA));
        $this->actingAs($user)->get(route('invoices.pdf.show', $invoiceB));

        $tokenA = $invoiceA->refresh()->verification_token;
        $tokenB = $invoiceB->refresh()->verification_token;

        $this->assertGreaterThanOrEqual(64, strlen($tokenA));
        $this->assertNotSame($tokenA, $tokenB);
        // A sequential/guessable token would differ only by a small suffix;
        // two independently random 64-char tokens share no long prefix.
        $this->assertNotSame(substr($tokenA, 0, 10), substr($tokenB, 0, 10));
    }

    public function test_an_unknown_verification_token_responds_with_a_safe_not_found_page(): void
    {
        $response = $this->get('/verificar-documento/'.str_repeat('x', 64));

        $response->assertNotFound();
        $response->assertDontSee('SQLSTATE');
        $response->assertDontSee('Stack trace', false);
    }

    public function test_the_public_verification_page_never_exposes_customer_contact_details(): void
    {
        $user = $this->facturador();
        $invoice = $this->issuedInvoiceFor($user);
        $this->actingAs($user)->get(route('invoices.pdf.show', $invoice));
        $invoice->refresh();

        $response = $this->get(route('documents.verify', ['token' => $invoice->verification_token]));

        $response->assertOk();
        $response->assertDontSee('cliente-secreto@example.com');
        $response->assertDontSee('3000000000');
        $response->assertDontSee('Calle secreta 123');
    }

    public function test_a_draft_invoice_has_no_working_verification_token_yet(): void
    {
        $user = $this->facturador();
        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $user->company_id;
        $invoice->save();

        $this->assertNull($invoice->verification_token);
    }

    public function test_pdf_download_is_blocked_for_a_different_company(): void
    {
        $userA = $this->facturador();
        $userB = $this->facturador();
        $invoiceB = $this->issuedInvoiceFor($userB);

        $this->actingAs($userA)->get(route('invoices.pdf.show', $invoiceB))->assertNotFound();
        $this->actingAs($userA)->get(route('invoices.pdf.download', $invoiceB))->assertNotFound();
    }

    public function test_pdf_download_requires_authentication(): void
    {
        $user = $this->facturador();
        $invoice = $this->issuedInvoiceFor($user);
        auth()->logout();

        $this->get(route('invoices.pdf.show', $invoice))->assertRedirect(route('login'));
    }

    public function test_pdf_cannot_be_downloaded_before_the_invoice_is_issued(): void
    {
        $user = $this->facturador();
        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $user->company_id;
        $invoice->save();

        $this->actingAs($user)->get(route('invoices.pdf.show', $invoice))->assertForbidden();
    }

    public function test_pdf_filename_is_sanitized_and_cannot_traverse_paths(): void
    {
        $user = $this->facturador();
        $invoice = $this->issuedInvoiceFor($user);

        $response = $this->actingAs($user)->get(route('invoices.pdf.download', $invoice));

        $response->assertOk();
        $disposition = $response->headers->get('Content-Disposition');

        $this->assertStringNotContainsString('..', $disposition);
        $this->assertStringNotContainsString('/', str_replace('attachment; filename="', '', $disposition));
        $this->assertMatchesRegularExpression('/^attachment; filename="[A-Za-z0-9_-]+\.pdf"$/', $disposition);
    }
}
