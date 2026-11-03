<?php

namespace Tests\Feature\Whatsapp;

use App\Models\Company;
use App\Models\User;
use App\Models\WhatsappBridgeEvent;
use App\Services\Whatsapp\WhatsappHmacSignatureVerifier;
use App\Services\Whatsapp\WhatsappLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappBridgeTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-bridge-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['whatsapp.enabled' => true, 'whatsapp.bridge_secret' => self::SECRET]);
    }

    private function admin(?Company $company = null): User
    {
        return User::factory()->for($company ?? Company::factory())->administrador()->create();
    }

    private function links(): WhatsappLinkService
    {
        return app(WhatsappLinkService::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{headers: array<string, string>, raw: string}
     */
    private function signed(array $payload, ?string $secret = null, ?string $timestamp = null): array
    {
        $raw = json_encode($payload);
        $timestamp ??= (string) time();
        $verifier = new WhatsappHmacSignatureVerifier($secret ?? self::SECRET, 300);

        return [
            'headers' => [
                'X-Fiscora-Timestamp' => $timestamp,
                'X-Fiscora-Signature' => $verifier->sign($timestamp, $raw),
            ],
            'raw' => $raw,
        ];
    }

    private function postBridge(array $payload, array $headerOverrides = []): \Illuminate\Testing\TestResponse
    {
        $signed = $this->signed($payload);

        return $this->withHeaders(array_merge($signed['headers'], $headerOverrides))
            ->postJson(route('api.integrations.whatsapp.bridge'), $payload);
    }

    public function test_bridge_disabled_rejects_the_request(): void
    {
        config(['whatsapp.enabled' => false]);

        $response = $this->postBridge(['wa_id' => '573001111111', 'message' => 'hola']);

        $response->assertStatus(503);
    }

    public function test_a_request_without_a_signature_fails(): void
    {
        $response = $this->postJson(route('api.integrations.whatsapp.bridge'), [
            'wa_id' => '573001111111',
            'message' => 'hola',
        ]);

        $response->assertStatus(401);
    }

    public function test_an_incorrect_signature_fails(): void
    {
        $payload = ['wa_id' => '573001111111', 'message' => 'hola'];
        $signed = $this->signed($payload, secret: 'a-completely-different-secret');

        $response = $this->withHeaders($signed['headers'])->postJson(route('api.integrations.whatsapp.bridge'), $payload);

        $response->assertStatus(401);
    }

    public function test_an_expired_timestamp_fails(): void
    {
        $payload = ['wa_id' => '573001111111', 'message' => 'hola'];
        $signed = $this->signed($payload, timestamp: (string) (time() - 10000));

        $response = $this->withHeaders($signed['headers'])->postJson(route('api.integrations.whatsapp.bridge'), $payload);

        $response->assertStatus(401);
    }

    public function test_a_valid_signature_is_accepted(): void
    {
        $response = $this->postBridge(['wa_id' => '573001111111', 'message' => 'hola']);

        $response->assertOk();
        $response->assertJson(['handled' => false]);
    }

    public function test_an_unlinked_wa_id_returns_handled_false_without_leaking_data(): void
    {
        $response = $this->postBridge(['wa_id' => '573002222222', 'message' => '¿Cuántas facturas tengo?']);

        $response->assertOk();
        $response->assertExactJson(['handled' => false]);
    }

    public function test_a_linked_wa_id_uses_the_correct_user(): void
    {
        $user = $this->admin();
        $code = $this->links()->generate($user)['code'];
        $this->links()->consume('573003333333', null, str_replace('FISCORA-', '', $code));

        $response = $this->postBridge(['wa_id' => '573003333333', 'message' => '¿Cuántas facturas tengo?']);

        $response->assertOk();
        $response->assertJson(['handled' => true, 'type' => 'assistant']);

        $event = WhatsappBridgeEvent::where('wa_id', '573003333333')->where('event', 'whatsapp_bridge_message_assistant')->first();
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame($user->company_id, $event->company_id);
    }

    /**
     * Every UserRole currently passes the `use-assistant` gate once
     * company_id is set — the realistic way a linked user ends up
     * failing it is losing their company assignment afterwards (e.g. an
     * administrative change), not at link time. Link normally first,
     * then simulate that directly, and confirm the bridge's own
     * defensive re-check (not just the gate someone could have skipped)
     * blocks it instead of either crashing or silently allowing access.
     */
    public function test_a_user_without_assistant_permission_cannot_use_it_through_the_bridge(): void
    {
        $user = $this->admin();
        $code = $this->links()->generate($user)['code'];
        $this->links()->consume('573004444444', null, str_replace('FISCORA-', '', $code));

        User::withoutGlobalScopes()->whereKey($user->id)->update(['company_id' => null]);

        $response = $this->postBridge(['wa_id' => '573004444444', 'message' => 'hola']);

        $response->assertOk();
        $response->assertJson(['handled' => true, 'type' => 'not_authorized']);
    }

    public function test_company_a_never_gets_company_bs_data_through_the_bridge(): void
    {
        $companyA = Company::factory()->create();
        $userA = User::factory()->for($companyA)->administrador()->create();
        $companyB = Company::factory()->create();
        $userB = User::factory()->for($companyB)->administrador()->create();

        $codeA = $this->links()->generate($userA)['code'];
        $this->links()->consume('573005555555', null, str_replace('FISCORA-', '', $codeA));
        $codeB = $this->links()->generate($userB)['code'];
        $this->links()->consume('573006666666', null, str_replace('FISCORA-', '', $codeB));

        $responseA = $this->postBridge(['wa_id' => '573005555555', 'message' => '¿Cuántas facturas tengo?']);
        $responseB = $this->postBridge(['wa_id' => '573006666666', 'message' => '¿Cuántas facturas tengo?']);

        $responseA->assertOk()->assertJson(['handled' => true, 'type' => 'assistant']);
        $responseB->assertOk()->assertJson(['handled' => true, 'type' => 'assistant']);

        $eventA = WhatsappBridgeEvent::where('wa_id', '573005555555')->first();
        $eventB = WhatsappBridgeEvent::where('wa_id', '573006666666')->first();

        $this->assertSame($companyA->id, $eventA->company_id);
        $this->assertSame($companyB->id, $eventB->company_id);
        $this->assertNotEquals($eventA->company_id, $eventB->company_id);
    }

    public function test_a_duplicate_message_id_never_executes_the_same_action_twice(): void
    {
        $user = $this->admin();
        $code = $this->links()->generate($user)['code'];
        $plain = str_replace('FISCORA-', '', $code);

        $payload = [
            'wa_id' => '573007777777',
            'message' => "VINCULAR FISCORA-{$plain}",
            'message_id' => 'wamid.DUPLICATE123',
        ];

        $first = $this->postBridge($payload);
        $first->assertOk();
        $first->assertJson(['handled' => true, 'type' => 'linked']);

        $connectionsAfterFirst = \App\Models\WhatsappConnection::withoutGlobalScopes()->count();

        $second = $this->postBridge($payload);
        $second->assertOk();
        // Identical cached response, byte for byte — not reprocessed.
        $this->assertSame($first->json(), $second->json());

        $this->assertSame(
            $connectionsAfterFirst,
            \App\Models\WhatsappConnection::withoutGlobalScopes()->count(),
            'El reenvío del mismo message_id no debió crear una segunda conexión.'
        );
    }
}
