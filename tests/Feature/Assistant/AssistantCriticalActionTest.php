<?php

namespace Tests\Feature\Assistant;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NumberingResolution;
use App\Models\ProductService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantCriticalActionTest extends TestCase
{
    use RefreshDatabase;

    private function facturador(?Company $company = null): User
    {
        return User::factory()->for($company ?? Company::factory())->facturador()->create();
    }

    private function draftInvoiceFor(User $user): Invoice
    {
        $invoice = new Invoice(['user_id' => $user->id]);
        $invoice->company_id = $user->company_id;
        $invoice->save();

        return $invoice;
    }

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

        return $invoice->refresh();
    }

    public function test_the_assistant_never_issues_an_invoice_directly_from_a_text_reply(): void
    {
        $user = $this->facturador();
        NumberingResolution::factory()->for($user->company)->create(['prefix' => 'FV', 'range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1]);
        $invoice = $this->locallyValidatedInvoice($user);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Quiero emitir esta factura',
            'screen' => 'invoice.show',
            'resource_type' => 'invoice',
            'resource_id' => $invoice->id,
        ])->assertOk();

        $this->assertTrue($response->json('requires_confirmation'));
        $token = $response->json('actions.0.token');
        $this->assertNotEmpty($token);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::LocallyValidated, $invoice->status);
    }

    public function test_confirming_the_token_executes_the_real_domain_service(): void
    {
        $user = $this->facturador();
        NumberingResolution::factory()->for($user->company)->create(['prefix' => 'FV', 'range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1]);
        $invoice = $this->locallyValidatedInvoice($user);

        $prepared = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Quiero emitir esta factura',
            'screen' => 'invoice.show',
            'resource_type' => 'invoice',
            'resource_id' => $invoice->id,
        ]);
        $token = $prepared->json('actions.0.token');

        $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token])->assertOk();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertNotNull($invoice->number);
    }

    public function test_an_invalid_or_unknown_token_is_rejected(): void
    {
        $user = $this->facturador();

        $this->actingAs($user)->postJson(route('assistant.confirm'), [
            'token' => str_repeat('x', 40),
        ])->assertStatus(422);
    }

    public function test_a_token_cannot_be_confirmed_by_a_different_user_or_company(): void
    {
        $userA = $this->facturador();
        NumberingResolution::factory()->for($userA->company)->create(['prefix' => 'FV', 'range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1]);
        $invoice = $this->locallyValidatedInvoice($userA);

        $prepared = $this->actingAs($userA)->postJson(route('assistant.message'), [
            'message' => 'Quiero emitir esta factura',
            'screen' => 'invoice.show',
            'resource_type' => 'invoice',
            'resource_id' => $invoice->id,
        ]);
        $token = $prepared->json('actions.0.token');

        $userB = $this->facturador();
        $this->actingAs($userB)->postJson(route('assistant.confirm'), ['token' => $token])->assertStatus(422);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::LocallyValidated, $invoice->status);
    }

    public function test_a_token_can_only_be_confirmed_once(): void
    {
        $user = $this->facturador();
        NumberingResolution::factory()->for($user->company)->create(['prefix' => 'FV', 'range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1]);
        $invoice = $this->locallyValidatedInvoice($user);

        $prepared = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Quiero emitir esta factura',
            'screen' => 'invoice.show',
            'resource_type' => 'invoice',
            'resource_id' => $invoice->id,
        ]);
        $token = $prepared->json('actions.0.token');

        $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token])->assertOk();
        $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token])->assertStatus(422);
    }

    public function test_a_facturador_without_permission_on_the_invoice_gets_no_confirmable_action(): void
    {
        $owner = $this->facturador();
        NumberingResolution::factory()->for($owner->company)->create(['prefix' => 'FV', 'range_from' => 1, 'range_to' => 100, 'current_consecutive' => 1]);
        $invoice = $this->locallyValidatedInvoice($owner);

        $contador = User::factory()->for($owner->company)->contador()->create();

        $response = $this->actingAs($contador)->postJson(route('assistant.message'), [
            'message' => 'Quiero emitir esta factura',
            'screen' => 'invoice.show',
            'resource_type' => 'invoice',
            'resource_id' => $invoice->id,
        ])->assertOk();

        $this->assertEmpty(array_filter(
            $response->json('actions') ?? [],
            fn (array $action): bool => ($action['action'] ?? null) === 'issue_invoice'
        ));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::LocallyValidated, $invoice->status);
    }
}
