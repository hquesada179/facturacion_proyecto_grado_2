<?php

namespace Tests\Feature\Whatsapp;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ProductService;
use App\Models\User;
use App\Models\WhatsappConnection;
use App\Services\Whatsapp\WhatsappHmacSignatureVerifier;
use App\Services\Whatsapp\WhatsappLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Proves WhatsApp is just another channel of the EXISTING assistant —
 * same AiAssistantService::handle(), same conversation/pending_action
 * mechanics, same confirm/cancel pipeline as
 * tests/Feature/Assistant/AssistantActionConversationTest.php's own web
 * version of these exact scenarios. Nothing here reimplements any of
 * that logic.
 */
class WhatsappAssistantContinuityTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'continuity-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['whatsapp.enabled' => true, 'whatsapp.bridge_secret' => self::SECRET]);
    }

    private function linkedConnection(?Company $company = null): WhatsappConnection
    {
        $user = User::factory()->for($company ?? Company::factory())->administrador()->create();
        $code = app(WhatsappLinkService::class)->generate($user)['code'];
        $outcome = app(WhatsappLinkService::class)->consume('573009990000', null, str_replace('FISCORA-', '', $code));

        return $outcome['connection'];
    }

    private function send(string $waId, string $message): TestResponse
    {
        $payload = ['wa_id' => $waId, 'message' => $message];
        $raw = json_encode($payload);
        $timestamp = (string) time();
        $verifier = new WhatsappHmacSignatureVerifier(self::SECRET, 300);

        return $this->withHeaders([
            'X-Fiscora-Timestamp' => $timestamp,
            'X-Fiscora-Signature' => $verifier->sign($timestamp, $raw),
        ])->postJson(route('api.integrations.whatsapp.bridge'), $payload);
    }

    public function test_a_simple_whatsapp_message_reaches_the_assistant_service(): void
    {
        $connection = $this->linkedConnection();

        $response = $this->send($connection->wa_id, '¿Cuántas facturas tengo?');

        $response->assertOk()->assertJson(['handled' => true, 'type' => 'assistant']);

        $message = AssistantMessage::where('role', 'user')->where('content', '¿Cuántas facturas tengo?')->first();
        $this->assertNotNull($message, 'El mensaje debió llegar a AiAssistantService y quedar registrado como siempre.');
    }

    public function test_the_conversation_is_created_once_and_reused_on_the_next_message(): void
    {
        $connection = $this->linkedConnection();

        $first = $this->send($connection->wa_id, '¿Cuántas facturas tengo?');
        $firstConversationId = $first->json('conversation_id');
        $this->assertNotNull($firstConversationId);
        $this->assertSame($firstConversationId, $connection->refresh()->assistant_conversation_id);

        $second = $this->send($connection->wa_id, '¿Y notas crédito?');
        $this->assertSame($firstConversationId, $second->json('conversation_id'), 'El segundo mensaje debió continuar la misma conversación.');
        $this->assertSame(1, AssistantConversation::withoutGlobalScopes()->where('user_id', $connection->user_id)->count());
    }

    public function test_a_read_only_query_works_over_whatsapp(): void
    {
        $connection = $this->linkedConnection();
        $user = $connection->user;
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);

        $response = $this->send($connection->wa_id, 'Busca al cliente Kata');

        $response->assertOk();
        $this->assertStringContainsString('Kata', $response->json('reply'));
    }

    public function test_an_action_proposal_keeps_the_pending_action_on_the_conversation(): void
    {
        $connection = $this->linkedConnection();
        $user = $connection->user;
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);
        ProductService::factory()->for($user->company)->create(['name' => 'Camisas', 'price' => '50000']);

        $response = $this->send($connection->wa_id, 'Prepárame una factura para Kata con 1 camisas.');

        $response->assertOk();
        $this->assertTrue((bool) $response->json('requires_confirmation'));

        $conversation = AssistantConversation::findOrFail($response->json('conversation_id'));
        $this->assertNotNull($conversation->pending_action);
        $this->assertSame('create_invoice_draft', $conversation->pending_action['action']);
    }

    public function test_a_later_confirmar_message_reuses_the_same_conversation_and_resolves_the_pending_action(): void
    {
        $connection = $this->linkedConnection();
        $user = $connection->user;
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);
        ProductService::factory()->for($user->company)->create(['name' => 'Camisas', 'price' => '50000']);

        $proposal = $this->send($connection->wa_id, 'Prepárame una factura para Kata con 1 camisas.');
        $conversationId = $proposal->json('conversation_id');

        $confirmed = $this->send($connection->wa_id, 'Confirmar.');

        $confirmed->assertOk();
        $this->assertSame($conversationId, $confirmed->json('conversation_id'));
        $this->assertDatabaseCount('invoices', 1);
        $this->assertNull(AssistantConversation::findOrFail($conversationId)->pending_action);
    }

    public function test_cancelar_works_over_whatsapp_without_creating_anything(): void
    {
        $connection = $this->linkedConnection();
        $user = $connection->user;
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);
        ProductService::factory()->for($user->company)->create(['name' => 'Camisas', 'price' => '50000']);

        $this->send($connection->wa_id, 'Prepárame una factura para Kata con 1 camisas.');
        $cancelled = $this->send($connection->wa_id, 'Cancelar.');

        $cancelled->assertOk();
        $this->assertDatabaseCount('invoices', 0);
    }

    /**
     * No second AI provider was introduced for WhatsApp: the stored
     * assistant message still reports the exact same provider/model the
     * web assistant uses under the test suite's own AI_ENABLED=false
     * configuration (see phpunit.xml) — LocalFallbackProvider, resolved
     * through the same AiProviderInterface binding in AppServiceProvider.
     */
    public function test_no_separate_ai_provider_was_created_for_whatsapp(): void
    {
        $connection = $this->linkedConnection();

        $this->send($connection->wa_id, '¿Cuántas facturas tengo?');

        $assistantMessage = AssistantMessage::where('role', 'assistant')->latest('id')->first();
        $this->assertSame('local_fallback', $assistantMessage->metadata['provider']);
    }
}
