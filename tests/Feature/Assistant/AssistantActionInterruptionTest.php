<?php

namespace Tests\Feature\Assistant;

use App\Models\AssistantActionProposal;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ProductService;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Regression coverage for two bugs found during manual IA 2.0-D
 * testing:
 *
 *  - bug #1: "2 camisas" never matched a product literally named
 *    "camisa" — FindProductTool's LIKE search has no plural awareness.
 *    Fixed in ProposeInvoiceDraftAction::resolveProductCandidates()
 *    with a bounded singular/plural retry, never a fuzzy/typo match.
 *
 *  - bug #2: once a pending_action existed, ANY later message —
 *    including an unrelated, perfectly well-formed read query — was
 *    routed back into finishing that action (AssistantActionOrchestrator
 *    ::resolve() fell back to `pendingAction['action']` whenever the
 *    message didn't match a NEW explicit trigger, with no check for
 *    whether it actually looked like a continuation). Fixed by probing
 *    the real read-only dispatcher BEFORE ever treating a message as a
 *    continuation — see AiAssistantService::handle()'s priority order.
 */
class AssistantActionInterruptionTest extends TestCase
{
    use RefreshDatabase;

    private function facturador(?Company $company = null): User
    {
        return User::factory()->for($company ?? Company::factory())->facturador()->create();
    }

    private function ask(User $user, string $message, ?int $conversationId = null): TestResponse
    {
        return $this->actingAs($user)->postJson(route('assistant.message'), array_filter([
            'message' => $message, 'screen' => 'dashboard', 'conversation_id' => $conversationId,
        ]));
    }

    private function hasProposal(TestResponse $response): bool
    {
        return array_filter($response->json('components') ?? [], fn (array $c): bool => $c['type'] === 'action_proposal') !== [];
    }

    // --- Bug #1: singular/plural product resolution --------------------------

    public function test_a_singular_product_name_is_found_from_a_plural_request(): void
    {
        $user = $this->facturador();
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);
        ProductService::factory()->for($user->company)->create(['name' => 'camisa', 'price' => '50000']);

        $response = $this->ask($user, 'Prepárame una factura para kata con 2 camisas.')->assertOk();

        $this->assertTrue($this->hasProposal($response));
        $this->assertSame('camisa', $response->json('components.0.lines.0.label'));
        $this->assertSame('×2', $response->json('components.0.lines.0.detail'));
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_another_singular_plural_pair_zapato_zapatos(): void
    {
        $user = $this->facturador();
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);
        ProductService::factory()->for($user->company)->create(['name' => 'zapato', 'price' => '80000']);

        $response = $this->ask($user, 'Prepárame una factura para kata con 2 zapatos.')->assertOk();

        $this->assertTrue($this->hasProposal($response));
        $this->assertSame('zapato', $response->json('components.0.lines.0.label'));
    }

    public function test_plural_normalization_never_bypasses_ambiguity(): void
    {
        $user = $this->facturador();
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);
        ProductService::factory()->for($user->company)->create(['name' => 'Zapato Casual', 'price' => '80000']);
        ProductService::factory()->for($user->company)->create(['name' => 'Zapato Deportivo', 'price' => '90000']);

        $response = $this->ask($user, 'Prepárame una factura para kata con 2 zapatos.')->assertOk();

        $this->assertFalse($this->hasProposal($response));
        $productList = collect($response->json('components'))->firstWhere('type', 'product_list');
        $this->assertNotNull($productList);
        $this->assertCount(2, $productList['items']);
        $this->assertDatabaseCount('assistant_action_proposals', 0);
    }

    public function test_an_unmatched_product_even_after_plural_normalization_is_a_safe_clarification_not_an_invention(): void
    {
        $user = $this->facturador();
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);

        $response = $this->ask($user, 'Prepárame una factura para kata con 2 productoquenoexiste.')->assertOk();

        $this->assertFalse($this->hasProposal($response));
        $this->assertStringContainsString('No encontré un producto', $response->json('message'));
        $this->assertDatabaseCount('assistant_action_proposals', 0);
    }

    // --- Bug #2: pending_action must never hijack an unrelated message -------

    public function test_scenario_1_full_sentence_with_a_real_product_reaches_a_proposal(): void
    {
        $user = $this->facturador();
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);
        ProductService::factory()->for($user->company)->create(['name' => 'camisa', 'price' => '50000']);

        $response = $this->ask($user, 'Prepárame una factura para kata con 2 camisas')->assertOk();

        $this->assertTrue($this->hasProposal($response));
    }

    public function test_scenario_2_a_failed_product_lookup_never_leaks_into_the_next_unrelated_query(): void
    {
        $user = $this->facturador();
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);

        $first = $this->ask($user, 'Prepárame una factura para kata con 2 productoquenoexiste')->assertOk();
        $this->assertStringContainsString('No encontré un producto', $first->json('message'));
        $cid = $first->json('conversation_id');

        $second = $this->ask($user, '¿Cuántas facturas tengo registradas?', $cid)->assertOk();

        $this->assertStringNotContainsString('No encontré un producto', $second->json('message'));
        $this->assertNotEmpty(array_filter($second->json('components'), fn (array $c): bool => $c['type'] === 'metric_summary'));
    }

    public function test_scenario_3_a_read_query_pauses_an_incomplete_action_which_can_then_be_resumed(): void
    {
        $user = $this->facturador();
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);
        ProductService::factory()->for($user->company)->create(['name' => 'camisa', 'price' => '50000']);

        $first = $this->ask($user, 'Prepárame una factura para kata')->assertOk();
        $this->assertStringContainsString('productos', mb_strtolower($first->json('message')));
        $cid = $first->json('conversation_id');

        $second = $this->ask($user, '¿Cuánto IVA llevo este mes?', $cid)->assertOk();
        $this->assertStringNotContainsString('producto', mb_strtolower($second->json('message')));

        $third = $this->ask($user, 'continuemos con la factura', $cid)->assertOk();
        $this->assertStringContainsString('productos', mb_strtolower($third->json('message')));

        $fourth = $this->ask($user, '2 camisas', $cid)->assertOk();
        $this->assertTrue($this->hasProposal($fourth));
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_scenario_4_cancelar_invalidates_the_live_proposal(): void
    {
        $user = $this->facturador();
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);
        ProductService::factory()->for($user->company)->create(['name' => 'camisa', 'price' => '50000']);

        $first = $this->ask($user, 'Prepárame una factura para kata con 2 camisas')->assertOk();
        $cid = $first->json('conversation_id');
        $token = $first->json('components.0.token');

        $second = $this->ask($user, 'Cancelar', $cid)->assertOk();
        $this->assertStringContainsString('cancelada', mb_strtolower($second->json('message')));

        $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token])->assertStatus(422);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_scenario_5_a_new_conversation_never_inherits_the_previous_pending_action(): void
    {
        $user = $this->facturador();
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);

        $this->ask($user, 'Prepárame una factura para kata con 2 productoquenoexiste')->assertOk();

        // No conversation_id -> brand new conversation.
        $response = $this->ask($user, '2 camisas')->assertOk();

        $this->assertFalse($this->hasProposal($response));
    }

    // --- Full manual-verification walkthrough from the bug report -----------

    public function test_full_walkthrough_propose_then_unrelated_count_query_without_corrupting_the_proposal(): void
    {
        $user = $this->facturador();
        $customer = Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);
        $tax = Tax::factory()->create(['code' => 'IVA19', 'rate' => '19']);
        $product = ProductService::factory()->for($user->company)->create(['name' => 'camisa', 'price' => '100000']);
        $product->taxes()->attach($tax->id);

        $proposeResponse = $this->ask($user, 'Prepárame una factura para kata con 2 camisas.')->assertOk();
        $proposal = $proposeResponse->json('components.0');
        $token = $proposal['token'];

        // Before confirming: no new invoice, proposal exists, customer/
        // product/quantity/totals are all backend-computed.
        $this->assertDatabaseCount('invoices', 0);
        $dbProposal = AssistantActionProposal::query()->where('token_hash', hash('sha256', $token))->first();
        $this->assertNotNull($dbProposal);
        $this->assertSame($customer->id, $dbProposal->payload['customer_id']);
        $this->assertSame($product->id, $dbProposal->payload['lines'][0]['product_service_id']);
        $this->assertSame('2', $dbProposal->payload['lines'][0]['quantity']);
        $this->assertStringContainsString('$ 200.000,00', collect($proposal['totals'])->firstWhere('label', 'Subtotal')['value']);
        $this->assertStringContainsString('$ 38.000,00', collect($proposal['totals'])->firstWhere('label', 'IVA / impuestos')['value']);

        // Ask an unrelated read question without confirming.
        $countResponse = $this->ask($user, '¿Cuántas facturas tengo registradas?', $proposeResponse->json('conversation_id'))->assertOk();
        $this->assertNotEmpty(array_filter($countResponse->json('components'), fn (array $c): bool => $c['type'] === 'metric_summary'));

        // The proposal must still be intact and confirmable afterwards.
        $dbProposal->refresh();
        $this->assertSame('pending', $dbProposal->status);
        $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token])->assertOk();
        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame(2, (int) Invoice::first()->items->first()->quantity);
    }
}
