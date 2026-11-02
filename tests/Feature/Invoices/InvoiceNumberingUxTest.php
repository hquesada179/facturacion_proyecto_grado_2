<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NumberingResolution;
use App\Models\ProductService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UX coverage for the "no hay resolución de numeración vigente" screen —
 * complements InvoiceIssuanceTest::test_issuance_without_an_active_resolution_results_in_technical_error,
 * which already locks down the backend's own authority
 * (IssueInvoiceService::finishIssuing()). Nothing here changes or
 * bypasses that check — every test still proves emission stays blocked
 * without a valid resolution.
 */
class InvoiceNumberingUxTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithCompany(?Company $company = null): User
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

    private function locallyValidatedInvoice(User $user): Invoice
    {
        $invoice = $this->draftInvoiceFor($user);
        $customer = Customer::factory()->for($user->company)->create(['status' => 'active']);
        $product = ProductService::factory()->for($user->company)->create(['price' => '100000.00']);

        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => $customer->id]);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), [
            'product_service_id' => $product->id,
            'quantity' => '1',
            'unit_price' => '100000',
        ]);
        $this->actingAs($user)->post(route('invoices.draft.validate', $invoice));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::LocallyValidated, $invoice->status);

        return $invoice;
    }

    public function test_the_validation_screen_shows_an_actionable_message_when_no_resolution_exists(): void
    {
        $user = $this->adminWithCompany();
        $invoice = $this->locallyValidatedInvoice($user);

        $response = $this->actingAs($user)->get(route('invoices.draft.validation', $invoice));

        $response->assertOk();
        $response->assertSee('Configura la numeración de facturas antes de emitir');
        $response->assertSee('resolución de numeración vigente');
        $response->assertSee('prototipo académico');
        $response->assertSee('no tiene validez tributaria real ante la DIAN');
    }

    public function test_the_cta_points_to_the_real_numbering_settings_route(): void
    {
        $user = $this->adminWithCompany();
        $invoice = $this->locallyValidatedInvoice($user);

        $response = $this->actingAs($user)->get(route('invoices.draft.validation', $invoice));

        $response->assertOk();
        $response->assertSee(route('settings.billing.numbering.create'), false);
        $response->assertSee('Configurar numeración');
    }

    public function test_the_emit_button_is_replaced_not_shown_when_resolution_is_missing(): void
    {
        $user = $this->adminWithCompany();
        $invoice = $this->locallyValidatedInvoice($user);

        $response = $this->actingAs($user)->get(route('invoices.draft.validation', $invoice));

        $response->assertOk();
        $response->assertDontSee('Emitir factura (simulado)');
    }

    public function test_the_emit_button_shows_normally_when_resolution_is_active(): void
    {
        $user = $this->adminWithCompany();
        NumberingResolution::factory()->for($user->company)->create([
            'prefix' => 'FV', 'range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1,
        ]);
        $invoice = $this->locallyValidatedInvoice($user);

        $response = $this->actingAs($user)->get(route('invoices.draft.validation', $invoice));

        $response->assertOk();
        $response->assertSee('Emitir factura (simulado)');
        $response->assertDontSee('Configura la numeración de facturas antes de emitir');
    }

    public function test_emission_is_still_blocked_at_the_backend_without_a_resolution(): void
    {
        $user = $this->adminWithCompany();
        $invoice = $this->locallyValidatedInvoice($user);

        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::TechnicalError, $invoice->status);
        $this->assertNull($invoice->number);
    }

    public function test_emission_succeeds_with_an_active_resolution(): void
    {
        $user = $this->adminWithCompany();
        NumberingResolution::factory()->for($user->company)->create([
            'prefix' => 'FV', 'range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1,
        ]);
        $invoice = $this->locallyValidatedInvoice($user);

        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertNotNull($invoice->number);
    }

    public function test_an_expired_resolution_does_not_count_as_active(): void
    {
        $user = $this->adminWithCompany();
        NumberingResolution::factory()->for($user->company)->create([
            'prefix' => 'FV',
            'range_from' => 1,
            'range_to' => 100,
            'current_consecutive' => 1,
            'valid_from' => now()->subYear()->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);
        $invoice = $this->locallyValidatedInvoice($user);

        $response = $this->actingAs($user)->get(route('invoices.draft.validation', $invoice));
        $response->assertSee('Configura la numeración de facturas antes de emitir');

        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::TechnicalError, $invoice->status);
    }

    public function test_a_future_resolution_does_not_count_as_active(): void
    {
        $user = $this->adminWithCompany();
        NumberingResolution::factory()->for($user->company)->create([
            'prefix' => 'FV',
            'range_from' => 1,
            'range_to' => 100,
            'current_consecutive' => 1,
            'valid_from' => now()->addDay()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
        ]);
        $invoice = $this->locallyValidatedInvoice($user);

        $response = $this->actingAs($user)->get(route('invoices.draft.validation', $invoice));
        $response->assertSee('Configura la numeración de facturas antes de emitir');

        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::TechnicalError, $invoice->status);
    }

    public function test_company_a_cannot_use_company_bs_resolution(): void
    {
        $userA = $this->adminWithCompany();
        $userB = $this->adminWithCompany();
        NumberingResolution::factory()->for($userB->company)->create([
            'prefix' => 'FV', 'range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1,
        ]);

        $invoiceA = $this->locallyValidatedInvoice($userA);

        $response = $this->actingAs($userA)->get(route('invoices.draft.validation', $invoiceA));
        $response->assertSee('Configura la numeración de facturas antes de emitir');

        $this->actingAs($userA)->post(route('invoices.draft.issue', $invoiceA));
        $invoiceA->refresh();
        $this->assertSame(InvoiceStatus::TechnicalError, $invoiceA->status);
    }

    public function test_a_non_administrador_sees_guidance_without_a_dead_end_link(): void
    {
        $company = Company::factory()->create();
        $facturador = User::factory()->for($company)->facturador()->create();
        $invoice = $this->locallyValidatedInvoice($facturador);

        $response = $this->actingAs($facturador)->get(route('invoices.draft.validation', $invoice));

        $response->assertOk();
        $response->assertSee('Configura la numeración de facturas antes de emitir');
        $response->assertDontSee(route('settings.billing.numbering.create'), false);
        $response->assertSee('Pide a un administrador');
    }
}
