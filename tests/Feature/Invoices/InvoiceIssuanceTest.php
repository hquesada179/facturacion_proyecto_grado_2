<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceHasBlockingIssuesException;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\NumberingResolution;
use App\Models\ProductService;
use App\Models\User;
use App\Services\Invoices\IssueInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceIssuanceTest extends TestCase
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

    /** Builds a draft with a customer and one valid item, through the real HTTP wizard. */
    private function readyDraft(User $user): Invoice
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

        return $invoice->refresh();
    }

    private function locallyValidatedInvoice(User $user): Invoice
    {
        $invoice = $this->readyDraft($user);
        $this->actingAs($user)->post(route('invoices.draft.validate', $invoice));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::LocallyValidated, $invoice->status);

        return $invoice;
    }

    public function test_validation_blocks_when_there_are_blocking_issues(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user); // no customer, no items

        $response = $this->actingAs($user)->post(route('invoices.draft.validate', $invoice));

        $response->assertRedirect(route('invoices.draft.validation', $invoice));
        $response->assertSessionHas('error');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertDatabaseHas('invoice_events', ['invoice_id' => $invoice->id, 'type' => 'local_validation_failed']);
    }

    public function test_validation_passes_and_transitions_to_locally_validated(): void
    {
        $user = $this->facturador();
        $invoice = $this->readyDraft($user);

        $response = $this->actingAs($user)->post(route('invoices.draft.validate', $invoice));

        $response->assertRedirect(route('invoices.draft.validation', $invoice));
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::LocallyValidated, $invoice->status);
        $this->assertDatabaseHas('invoice_events', [
            'invoice_id' => $invoice->id,
            'type' => 'local_validation_passed',
            'from_status' => 'draft',
            'to_status' => 'locally_validated',
        ]);
    }

    public function test_cannot_issue_without_being_locally_validated_first(): void
    {
        $user = $this->facturador();
        $invoice = $this->readyDraft($user); // still "draft", never validated

        $response = $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));

        $response->assertRedirect(route('invoices.draft.validation', $invoice));
        $response->assertSessionHas('error');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertNull($invoice->number);
    }

    public function test_full_happy_path_issues_with_a_definitive_number_and_snapshots(): void
    {
        $user = $this->facturador();
        NumberingResolution::factory()->for($user->company)->create(['prefix' => 'FV', 'range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1]);
        $invoice = $this->locallyValidatedInvoice($user);

        $response = $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));

        $invoice->refresh();
        $response->assertRedirect(route('invoices.show', $invoice));

        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame('FV-000001', $invoice->number);
        $this->assertNotNull($invoice->issued_at);
        $this->assertNotNull($invoice->validation_at);
        $this->assertSame('validada', $invoice->dian_simulation_result);
        $this->assertStringStartsWith('SIM-', $invoice->simulated_cufe);

        $this->assertSame($user->company->name, $invoice->issuer_snapshot['name']);
        $this->assertSame($user->company->nit, $invoice->issuer_snapshot['nit']);
        $this->assertSame($invoice->customer->name, $invoice->customer_snapshot['name']);
        $this->assertSame($invoice->customer->identification_number, $invoice->customer_snapshot['identification_number']);

        $types = InvoiceEvent::where('invoice_id', $invoice->id)->orderBy('id')->pluck('type')->all();
        $this->assertSame([
            // draftInvoiceFor() builds the draft directly, bypassing the
            // controller action that logs draft_created — see
            // test_creating_a_draft_creates_a_real_invoice_in_draft_status
            // in InvoiceDraftWizardTest for that event.
            'customer_selected',
            'item_added',
            'local_validation_passed',
            'issuance_requested',
            'simulated_validation_started',
            'simulated_validation_approved',
            'issued',
        ], $types);

        $issuedEvent = InvoiceEvent::where('invoice_id', $invoice->id)->where('type', 'issued')->first();
        $this->assertSame('sending_simulated', $issuedEvent->from_status);
        $this->assertSame('issued', $issuedEvent->to_status);
        $this->assertSame($user->id, $issuedEvent->user_id);
    }

    public function test_two_consecutive_issuances_do_not_reuse_numbers(): void
    {
        $user = $this->facturador();
        NumberingResolution::factory()->for($user->company)->create(['prefix' => 'FV', 'range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1]);

        $invoiceA = $this->locallyValidatedInvoice($user);
        $this->actingAs($user)->post(route('invoices.draft.issue', $invoiceA));
        $invoiceA->refresh();

        $invoiceB = $this->locallyValidatedInvoice($user);
        $this->actingAs($user)->post(route('invoices.draft.issue', $invoiceB));
        $invoiceB->refresh();

        $this->assertSame('FV-000001', $invoiceA->number);
        $this->assertSame('FV-000002', $invoiceB->number);
        $this->assertNotSame($invoiceA->number, $invoiceB->number);
    }

    public function test_issuance_without_an_active_resolution_results_in_technical_error(): void
    {
        $user = $this->facturador();
        // No NumberingResolution for this company.
        $invoice = $this->locallyValidatedInvoice($user);

        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::TechnicalError, $invoice->status);
        $this->assertNull($invoice->number);
        $this->assertSame('error', $invoice->dian_simulation_result);
        $this->assertDatabaseHas('invoice_events', ['invoice_id' => $invoice->id, 'type' => 'technical_error']);
    }

    public function test_simulated_rejection_does_not_consume_numbering(): void
    {
        $user = $this->facturador();
        $resolution = NumberingResolution::factory()->for($user->company)->create(['current_consecutive' => 1]);
        $invoice = $this->locallyValidatedInvoice($user);

        $service = app(IssueInvoiceService::class);
        $result = $service->issue($invoice, 'rechazada');

        $this->assertSame(InvoiceStatus::SimulatedRejected, $result->status);
        $this->assertNull($result->number);
        $this->assertSame('rechazada', $result->dian_simulation_result);

        $resolution->refresh();
        $this->assertSame(1, $resolution->current_consecutive);
        $this->assertDatabaseHas('invoice_events', ['invoice_id' => $invoice->id, 'type' => 'simulated_validation_rejected']);
    }

    public function test_simulated_technical_error_scenario_does_not_consume_numbering(): void
    {
        $user = $this->facturador();
        $resolution = NumberingResolution::factory()->for($user->company)->create(['current_consecutive' => 1]);
        $invoice = $this->locallyValidatedInvoice($user);

        $service = app(IssueInvoiceService::class);
        $result = $service->issue($invoice, 'error');

        $this->assertSame(InvoiceStatus::TechnicalError, $result->status);
        $this->assertNull($result->number);

        $resolution->refresh();
        $this->assertSame(1, $resolution->current_consecutive);
    }

    public function test_rejected_invoice_can_be_reopened_and_reissued(): void
    {
        $user = $this->facturador();
        NumberingResolution::factory()->for($user->company)->create(['current_consecutive' => 1]);
        $invoice = $this->locallyValidatedInvoice($user);

        app(IssueInvoiceService::class)->issue($invoice, 'rechazada');
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::SimulatedRejected, $invoice->status);

        // Touching the draft (e.g. re-selecting the customer) reopens it.
        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), [
            'customer_id' => $invoice->customer_id,
        ]);
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);

        $this->actingAs($user)->post(route('invoices.draft.validate', $invoice));
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::LocallyValidated, $invoice->status);

        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
    }

    public function test_issue_service_throws_when_blocking_issues_are_present(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);
        $invoice->status = InvoiceStatus::LocallyValidated;
        $invoice->save();

        $this->expectException(InvoiceHasBlockingIssuesException::class);
        app(IssueInvoiceService::class)->issue($invoice);
    }

    public function test_discarding_a_draft_locks_it(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user);

        $this->actingAs($user)->post(route('invoices.draft.discard', $invoice))->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Discarded, $invoice->status);

        $this->actingAs($user)
            ->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => 1])
            ->assertForbidden();
    }
}
