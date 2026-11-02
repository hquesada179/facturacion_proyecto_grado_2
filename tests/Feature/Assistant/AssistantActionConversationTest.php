<?php

namespace Tests\Feature\Assistant;

use App\Models\AssistantActionProposal;
use App\Models\AssistantConversation;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ProductService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AssistantActionConversationTest extends TestCase
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

    public function test_pending_action_is_stored_separately_from_conversation_memory(): void
    {
        $user = $this->facturador();
        ProductService::factory()->for($user->company)->create(['name' => 'Camisas', 'price' => '50000']);

        $response = $this->ask($user, 'Prepárame una factura con 2 camisas.')->assertOk();

        $conversation = AssistantConversation::findOrFail($response->json('conversation_id'));
        $this->assertNotNull($conversation->pending_action);
        $this->assertSame('create_invoice_draft', $conversation->pending_action['action']);
        // A pure action draft never populates the read-only memory.
        $this->assertTrue($conversation->conversation_memory === null || ($conversation->conversation_memory['entity_type'] ?? null) === null);
    }

    public function test_an_unrelated_read_question_abandons_the_pending_action(): void
    {
        $user = $this->facturador();
        ProductService::factory()->for($user->company)->create(['name' => 'Camisas', 'price' => '50000']);

        $first = $this->ask($user, 'Prepárame una factura con 2 camisas.')->assertOk();
        $cid = $first->json('conversation_id');

        $this->ask($user, '¿Cuánto IVA he generado?', $cid)->assertOk();

        // Naming the customer now must NOT silently complete the old,
        // abandoned invoice-draft intent.
        $third = $this->ask($user, 'Kata.', $cid)->assertOk();
        $this->assertEmpty(array_filter($third->json('components'), fn (array $c): bool => $c['type'] === 'action_proposal'));
    }

    public function test_confirming_via_chat_text_goes_through_the_same_secure_confirm_path(): void
    {
        // "Confirmar." typed in chat must go through the exact same
        // claim -> revalidate -> execute pipeline as the button — never
        // a shortcut that skips revalidation.
        $user = $this->facturador();
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);
        ProductService::factory()->for($user->company)->create(['name' => 'Camisas', 'price' => '50000']);

        $first = $this->ask($user, 'Prepárame una factura para Kata con 1 camisas.')->assertOk();
        $cid = $first->json('conversation_id');

        $second = $this->ask($user, 'Confirmar.', $cid)->assertOk();

        $this->assertDatabaseCount('invoices', 1);
        $this->assertStringContainsString('Borrador creado correctamente', $second->json('message'));
    }

    public function test_confirming_via_chat_text_twice_never_duplicates_the_record(): void
    {
        $user = $this->facturador();
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);
        ProductService::factory()->for($user->company)->create(['name' => 'Camisas', 'price' => '50000']);

        $first = $this->ask($user, 'Prepárame una factura para Kata con 1 camisas.')->assertOk();
        $cid = $first->json('conversation_id');

        $this->ask($user, 'Confirmar.', $cid)->assertOk();
        $this->ask($user, 'Confirmar.', $cid)->assertOk();

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_cancelling_via_chat_text_invalidates_the_proposal(): void
    {
        $user = $this->facturador();
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);
        ProductService::factory()->for($user->company)->create(['name' => 'Camisas', 'price' => '50000']);

        $first = $this->ask($user, 'Prepárame una factura para Kata con 1 camisas.')->assertOk();
        $cid = $first->json('conversation_id');
        $token = $first->json('components.0.token');

        $second = $this->ask($user, 'Cancelar.', $cid)->assertOk();
        $this->assertStringContainsString('cancelada', mb_strtolower($second->json('message')));

        $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token])->assertStatus(422);
    }

    public function test_an_expired_proposal_prompts_for_a_fresh_preview(): void
    {
        $user = $this->facturador();
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);
        ProductService::factory()->for($user->company)->create(['name' => 'Camisas', 'price' => '50000']);

        $first = $this->ask($user, 'Prepárame una factura para Kata con 1 camisas.')->assertOk();
        $cid = $first->json('conversation_id');

        AssistantActionProposal::query()->update(['expires_at' => now()->subMinute()]);

        $second = $this->ask($user, 'Confirmar.', $cid)->assertOk();
        $this->assertStringContainsString('expiró', $second->json('message'));
    }

    public function test_a_new_conversation_never_inherits_another_conversations_pending_action(): void
    {
        $user = $this->facturador();
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);
        ProductService::factory()->for($user->company)->create(['name' => 'Camisas', 'price' => '50000']);

        $this->ask($user, 'Prepárame una factura para Kata con 1 camisas.')->assertOk();

        // No conversation_id -> a brand new conversation/pending_action.
        $this->ask($user, 'Confirmar.')->assertOk();

        $this->assertDatabaseCount('invoices', 0);
    }
}
