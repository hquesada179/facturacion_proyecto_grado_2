<?php

namespace Tests\Feature\Assistant;

use App\Enums\CreditNoteStatus;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NumberingResolution;
use App\Models\ProductService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AssistantCreditNoteDraftActionTest extends TestCase
{
    use RefreshDatabase;

    private function facturador(?Company $company = null): User
    {
        return User::factory()->for($company ?? Company::factory())->facturador()->create();
    }

    private function ask(User $user, string $message): TestResponse
    {
        return $this->actingAs($user)->postJson(route('assistant.message'), ['message' => $message, 'screen' => 'dashboard']);
    }

    private function issuedInvoice(User $user, int $qty = 2): Invoice
    {
        NumberingResolution::factory()->for($user->company)->create(['prefix' => 'FV', 'range_from' => 1, 'range_to' => 1000, 'current_consecutive' => 1]);
        $customer = Customer::factory()->for($user->company)->create(['status' => 'active']);
        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $user->company_id;
        $invoice->save();
        $product = ProductService::factory()->for($user->company)->create(['price' => '100000.00']);
        $this->actingAs($user)->post(route('invoices.draft.customer.update', $invoice), ['customer_id' => $customer->id]);
        $this->actingAs($user)->post(route('invoices.draft.items.store', $invoice), ['product_service_id' => $product->id, 'quantity' => (string) $qty, 'unit_price' => '100000']);
        $this->actingAs($user)->post(route('invoices.draft.validate', $invoice));
        $this->actingAs($user)->post(route('invoices.draft.issue', $invoice));

        return $invoice->refresh();
    }

    public function test_total_void_preview_shows_the_full_remaining_balance(): void
    {
        $user = $this->facturador();
        $invoice = $this->issuedInvoice($user, 2);

        $response = $this->ask($user, 'Prepárame una nota crédito total para '.$invoice->number.'.')->assertOk();
        $proposal = $response->json('components.0');

        $this->assertSame('create_credit_note_draft', $proposal['action']);
        $this->assertSame($invoice->number, collect($proposal['fields'])->firstWhere('label', 'Factura origen')['value']);
        $this->assertStringContainsString('$ 200.000,00', collect($proposal['totals'])->firstWhere('label', 'Total acreditado')['value']);
        $this->assertDatabaseCount('credit_notes', 0);
    }

    public function test_confirming_creates_only_a_draft_never_validates_never_issues(): void
    {
        $user = $this->facturador();
        $invoice = $this->issuedInvoice($user, 2);

        $token = $this->ask($user, 'Prepárame una nota crédito total para '.$invoice->number.'.')->json('components.0.token');
        $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token])->assertOk();

        $creditNote = CreditNote::first();
        $this->assertSame(CreditNoteStatus::Draft, $creditNote->status);
        $this->assertNull($creditNote->number);
    }

    public function test_requesting_more_than_the_acreditable_balance_is_rejected(): void
    {
        $user = $this->facturador();
        $invoice = $this->issuedInvoice($user, 1);

        $response = $this->ask($user, 'Quiero devolver 5 unidades de la factura '.$invoice->number.'.')->assertOk();

        $this->assertEmpty(array_filter($response->json('components'), fn (array $c): bool => $c['type'] === 'action_proposal'));
    }

    public function test_a_draft_invoice_cannot_receive_a_credit_note_proposal(): void
    {
        $user = $this->facturador();
        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $user->company_id;
        $invoice->number = null;
        $invoice->save();

        $response = $this->ask($user, 'Prepárame una nota crédito total para la factura inexistente FV-000999.')->assertOk();

        $this->assertEmpty(array_filter($response->json('components'), fn (array $c): bool => $c['type'] === 'action_proposal'));
        $this->assertStringContainsString('No encontré la factura', $response->json('message'));
    }

    public function test_confirm_rechecks_the_balance_fresh_if_another_credit_note_consumed_it_meanwhile(): void
    {
        $user = $this->facturador();
        $invoice = $this->issuedInvoice($user, 2);

        $token = $this->ask($user, 'Prepárame una nota crédito total para '.$invoice->number.'.')->json('components.0.token');

        // Another credit note (e.g. created manually) consumes the whole balance first.
        $item = $invoice->items()->firstOrFail();
        NumberingResolution::factory()->for($user->company)->creditNote()->create(['prefix' => 'NC', 'range_from' => 1, 'range_to' => 1000, 'current_consecutive' => 1]);
        $this->actingAs($user)->post(route('credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'reason_code' => 'PROTO_TOTAL_VOID',
            'reason_text' => 'Anulación manual previa',
            'items' => [$item->id => ['quantity' => (string) $item->quantity]],
        ]);
        $this->actingAs($user)->post(route('credit-notes.validate', CreditNote::latest('id')->first()));
        $this->actingAs($user)->post(route('credit-notes.issue', CreditNote::latest('id')->first()));

        $response = $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token]);
        $response->assertStatus(422);
        $this->assertDatabaseCount('credit_notes', 1);
    }
}
