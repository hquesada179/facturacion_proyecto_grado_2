<?php

namespace Tests\Feature\Assistant;

use App\Models\AssistantConversation;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ProductService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantSecurityAndContextTest extends TestCase
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

    /** Draft with a customer and one valid item, built through the real HTTP wizard. */
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

    public function test_guests_cannot_reach_the_assistant_endpoint(): void
    {
        $this->postJson(route('assistant.message'), [
            'message' => 'Hola',
            'screen' => 'dashboard',
        ])->assertUnauthorized();
    }

    public function test_user_without_a_company_is_forbidden(): void
    {
        $user = User::factory()->create(['company_id' => null]);

        $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Hola',
            'screen' => 'dashboard',
        ])->assertForbidden();
    }

    public function test_every_business_role_can_use_the_assistant(): void
    {
        foreach (['administrador', 'facturador', 'contador', 'auditor'] as $role) {
            $user = User::factory()->for(Company::factory())->{$role}()->create();

            $this->actingAs($user)->postJson(route('assistant.message'), [
                'message' => 'Hola',
                'screen' => 'dashboard',
            ])->assertOk();
        }
    }

    public function test_it_validates_the_request_payload_strictly(): void
    {
        $user = $this->facturador();

        $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => '',
            'screen' => 'dashboard',
        ])->assertUnprocessable();

        $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Hola',
            'screen' => 'dashboard',
            'resource_type' => 'invalid_type',
        ])->assertUnprocessable();

        $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Hola',
            'screen' => 'inv@lid screen!',
        ])->assertUnprocessable();
    }

    public function test_an_arbitrary_resource_id_from_another_company_is_never_resolved(): void
    {
        $companyB = Company::factory()->create();
        $otherUser = User::factory()->for($companyB)->facturador()->create();
        $otherInvoice = $this->readyDraft($otherUser);

        $user = $this->facturador();

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Dame el detalle de esta factura',
            'screen' => 'invoice.show',
            'resource_type' => 'invoice',
            'resource_id' => $otherInvoice->id,
        ])->assertOk();

        $conversation = AssistantConversation::find($response->json('conversation_id'));

        $this->assertNotContains('invoice', $conversation->screen_context['shared_context_types']);
        $this->assertStringNotContainsString((string) $otherInvoice->id, $response->json('message'));
    }

    public function test_rate_limiting_blocks_excessive_requests_from_the_same_user(): void
    {
        $user = $this->facturador();

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($user)->postJson(route('assistant.message'), [
                'message' => 'Pregunta número '.$i,
                'screen' => 'dashboard',
            ])->assertOk();
        }

        $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Una pregunta de más',
            'screen' => 'dashboard',
        ])->assertStatus(429);
    }

    public function test_context_reflects_blocking_validation_state_for_the_current_invoice(): void
    {
        $user = $this->facturador();
        $invoice = $this->draftInvoiceFor($user); // no customer, no items -> blocking rules fire

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => '¿Por qué no puedo emitir esta factura?',
            'screen' => 'invoice.validation',
            'resource_type' => 'invoice',
            'resource_id' => $invoice->id,
        ])->assertOk();

        $this->assertStringContainsString('bloqueo', $response->json('message'));
    }

    public function test_context_reports_zero_blocking_issues_for_a_ready_invoice(): void
    {
        $user = $this->facturador();
        $invoice = $this->readyDraft($user);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => '¿Por qué no puedo emitir esta factura?',
            'screen' => 'invoice.validation',
            'resource_type' => 'invoice',
            'resource_id' => $invoice->id,
        ])->assertOk();

        $this->assertStringContainsString('no tiene bloqueos', $response->json('message'));
    }
}
