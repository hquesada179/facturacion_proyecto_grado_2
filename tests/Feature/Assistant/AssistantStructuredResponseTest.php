<?php

namespace Tests\Feature\Assistant;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NumberingResolution;
use App\Models\ProductService;
use App\Models\User;
use App\Services\Assistant\AssistantResponse;
use App\Services\Assistant\Contracts\AiProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * IA 2.0-A: `components` is a brand-new field on the assistant response
 * contract. No tool populates it yet (that is a later phase) — these tests
 * only lock in that the field always exists, is always an array, is empty
 * today in every response shape the system can currently produce, and
 * never becomes a vector for cross-tenant data.
 */
class AssistantStructuredResponseTest extends TestCase
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

    public function test_assistant_response_components_defaults_to_an_empty_array(): void
    {
        $response = new AssistantResponse('Hola');

        $this->assertSame([], $response->components);
        $this->assertSame([], $response->toArray()['components']);
    }

    public function test_components_field_is_present_and_empty_in_an_ordinary_reply(): void
    {
        $user = $this->facturador();

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Hola, ¿qué puedes hacer?',
            'screen' => 'dashboard',
        ])->assertOk();

        $response->assertJsonStructure(['message', 'suggestions', 'actions', 'requires_confirmation', 'components', 'conversation_id', 'message_id']);
        $this->assertSame([], $response->json('components'));
    }

    public function test_components_field_is_present_and_empty_when_the_external_provider_fails(): void
    {
        $user = $this->facturador();

        $this->app->bind(AiProviderInterface::class, fn () => new class implements AiProviderInterface
        {
            public function generate(array $context, string $message, array $toolResults = []): AssistantResponse
            {
                throw new RuntimeException('Simulated external outage');
            }

            public function name(): string
            {
                return 'external_demo';
            }

            public function modelIdentifier(): string
            {
                return 'external-demo-model';
            }
        });

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Hola',
            'screen' => 'dashboard',
        ])->assertOk();

        $this->assertStringContainsString('no está disponible temporalmente', $response->json('message'));
        $this->assertSame([], $response->json('components'));
    }

    public function test_components_field_is_present_and_empty_in_a_critical_action_confirmation_card(): void
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
        $this->assertSame([], $response->json('components'));
    }

    public function test_components_field_is_present_and_empty_after_confirming_a_token(): void
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

        $confirmed = $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token])->assertOk();

        $this->assertSame([], $confirmed->json('components'));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
    }

    public function test_raw_markdown_characters_from_the_model_reach_the_response_unchanged(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_fake_markdown',
                'object' => 'response',
                'output_text' => "**Resumen**\n\n- Primer punto\n- Segundo punto",
            ]),
        ]);

        config([
            'ai.enabled' => true,
            'ai.provider' => 'openai',
            'ai.openai.api_key' => 'sk-test-fake-key-000',
            'ai.openai.model' => 'gpt-4o-mini',
            'ai.openai.base_url' => 'https://api.openai.com/v1',
            'ai.openai.timeout' => 30,
        ]);

        $user = $this->facturador();

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Hola',
            'screen' => 'dashboard',
        ])->assertOk();

        // The backend never strips or rewrites Markdown — rendering is a
        // purely client-side concern (resources/js/assistant.js). This
        // response is the exact input that used to show up as raw
        // "**Resumen**" text before IA 2.0-A's frontend renderer existed.
        $this->assertSame("**Resumen**\n\n- Primer punto\n- Segundo punto", $response->json('message'));
        $this->assertSame([], $response->json('components'));
    }

    public function test_components_never_carries_cross_tenant_data_for_an_unreachable_resource(): void
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

        $this->assertSame([], $response->json('components'));
        $this->assertStringNotContainsString((string) $otherInvoice->id, json_encode($response->json('components')));
    }
}
