<?php

namespace Tests\Feature\Assistant;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AssistantCustomerActionTest extends TestCase
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

    public function test_preview_shows_name_person_type_and_identification(): void
    {
        $user = $this->facturador();

        $response = $this->ask($user, 'Prepara un cliente llamado Pedro Pérez, CC 123456789.')->assertOk();
        $proposal = $response->json('components.0');

        $this->assertSame('action_proposal', $proposal['type']);
        $this->assertSame('create_customer', $proposal['action']);
        $this->assertSame('Pedro Pérez', collect($proposal['fields'])->firstWhere('label', 'Nombre')['value']);
        $this->assertSame('CC 123456789', collect($proposal['fields'])->firstWhere('label', 'Identificación')['value']);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_confirming_creates_the_customer_in_the_users_company(): void
    {
        $user = $this->facturador();

        $token = $this->ask($user, 'Prepara un cliente llamado Pedro Pérez, CC 123456789.')->json('components.0.token');
        $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token])->assertOk();

        $customer = Customer::first();
        $this->assertSame('Pedro Pérez', $customer->name);
        $this->assertSame($user->company_id, $customer->company_id);
    }

    public function test_a_duplicate_identification_in_the_same_company_is_rejected_at_proposal_time(): void
    {
        $user = $this->facturador();
        Customer::factory()->for($user->company)->create(['identification_type' => 'CC', 'identification_number' => '123456789']);

        $response = $this->ask($user, 'Prepara un cliente llamado Pedro Pérez, CC 123456789.')->assertOk();

        $this->assertEmpty(array_filter($response->json('components'), fn (array $c): bool => $c['type'] === 'action_proposal'));
        $this->assertStringContainsString('ya ha sido registrado', $response->json('message'));
    }

    public function test_a_duplicate_created_between_propose_and_confirm_is_caught_at_confirm_time(): void
    {
        $user = $this->facturador();

        $token = $this->ask($user, 'Prepara un cliente llamado Pedro Pérez, CC 123456789.')->json('components.0.token');

        Customer::factory()->for($user->company)->create(['identification_type' => 'CC', 'identification_number' => '123456789']);

        $response = $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token]);
        $response->assertStatus(422);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_missing_identification_asks_and_the_next_turn_completes_it(): void
    {
        $user = $this->facturador();

        $first = $this->ask($user, 'Crea un cliente Juan.')->assertOk();
        $this->assertSame([], $first->json('components'));
        $this->assertStringContainsString('tipo y número', mb_strtolower($first->json('message')));

        $second = $this->ask($user, 'CC 12345678.', $first->json('conversation_id'))->assertOk();
        $proposal = $second->json('components.0');
        $this->assertSame('create_customer', $proposal['action'] ?? null);
        $this->assertSame('Juan', collect($proposal['fields'])->firstWhere('label', 'Nombre')['value']);
    }

    public function test_a_nit_infers_juridica_person_type(): void
    {
        $user = $this->facturador();

        $response = $this->ask($user, 'Prepara un cliente llamado Comercializadora SAS con NIT 900373115.')->assertOk();
        $proposal = $response->json('components.0');

        $this->assertSame('Jurídica', collect($proposal['fields'])->firstWhere('label', 'Tipo de persona')['value']);
    }
}
