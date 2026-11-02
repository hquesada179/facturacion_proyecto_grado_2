<?php

namespace Tests\Feature\Assistant;

use App\Models\Company;
use App\Models\Customer;
use App\Models\ProductService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * IA 2.0-D must never grant an action a human user of that role could
 * not already perform manually — Administrador/Facturador have
 * `manage-invoicing`; Auditor and Contador do not, and must be denied
 * identically through the assistant.
 */
class AssistantActionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function ask(User $user, string $message): TestResponse
    {
        return $this->actingAs($user)->postJson(route('assistant.message'), ['message' => $message, 'screen' => 'dashboard']);
    }

    private function hasProposal(TestResponse $response): bool
    {
        return array_filter($response->json('components') ?? [], fn (array $c): bool => $c['type'] === 'action_proposal') !== [];
    }

    public static function allowedRoleProvider(): array
    {
        return [['administrador'], ['facturador']];
    }

    public static function deniedRoleProvider(): array
    {
        return [['auditor'], ['contador']];
    }

    #[DataProvider('allowedRoleProvider')]
    public function test_allowed_roles_can_propose_a_customer(string $role): void
    {
        $user = User::factory()->for(Company::factory())->{$role}()->create();

        $response = $this->ask($user, 'Prepara un cliente llamado Pedro Pérez, CC 123456789.')->assertOk();

        $this->assertTrue($this->hasProposal($response));
    }

    #[DataProvider('deniedRoleProvider')]
    public function test_denied_roles_cannot_propose_a_customer(string $role): void
    {
        $user = User::factory()->for(Company::factory())->{$role}()->create();

        $response = $this->ask($user, 'Prepara un cliente llamado Pedro Pérez, CC 123456789.')->assertOk();

        $this->assertFalse($this->hasProposal($response));
        $this->assertDatabaseCount('assistant_action_proposals', 0);
    }

    #[DataProvider('deniedRoleProvider')]
    public function test_denied_roles_cannot_propose_a_product(string $role): void
    {
        $user = User::factory()->for(Company::factory())->{$role}()->create();

        $response = $this->ask($user, 'Prepara un producto Zapatos por $180.000 exento.')->assertOk();

        $this->assertFalse($this->hasProposal($response));
    }

    #[DataProvider('deniedRoleProvider')]
    public function test_denied_roles_cannot_propose_an_invoice_draft(string $role): void
    {
        $user = User::factory()->for(Company::factory())->{$role}()->create();
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);
        ProductService::factory()->for($user->company)->create(['name' => 'Camisas', 'price' => '50000']);

        $response = $this->ask($user, 'Prepárame una factura para Kata con 1 camisas.')->assertOk();

        $this->assertFalse($this->hasProposal($response));
    }

    public function test_a_role_without_manage_invoicing_cannot_confirm_even_with_a_valid_token_from_before_a_role_change(): void
    {
        // Simulates a role downgrade between propose and confirm: the
        // token alone must never be sufficient — confirm re-checks the
        // policy fresh, exactly like propose did.
        $user = User::factory()->for(Company::factory())->facturador()->create();
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);
        ProductService::factory()->for($user->company)->create(['name' => 'Camisas', 'price' => '50000']);

        $token = $this->ask($user, 'Prepárame una factura para Kata con 1 camisas.')->json('components.0.token');

        $user->forceFill(['role' => \App\Enums\UserRole::Auditor])->save();

        $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token])->assertStatus(422);
        $this->assertDatabaseCount('invoices', 0);
    }
}
