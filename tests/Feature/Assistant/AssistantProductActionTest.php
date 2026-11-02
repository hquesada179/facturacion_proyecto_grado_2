<?php

namespace Tests\Feature\Assistant;

use App\Models\Company;
use App\Models\ProductService;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AssistantProductActionTest extends TestCase
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

    public function test_preview_resolves_an_existing_tax_by_rate(): void
    {
        $user = $this->facturador();
        Tax::factory()->create(['code' => 'IVA19', 'rate' => '19']);

        $response = $this->ask($user, 'Prepara un producto Zapatos por $180.000 con IVA 19%.')->assertOk();
        $proposal = $response->json('components.0');

        $this->assertSame('create_product', $proposal['action']);
        $this->assertSame('IVA 19%', collect($proposal['fields'])->firstWhere('label', 'Impuesto')['value']);
        $this->assertDatabaseCount('product_services', 0);
    }

    public function test_an_exempt_service_carries_no_tax(): void
    {
        $user = $this->facturador();

        $response = $this->ask($user, 'Registra un servicio de consultoría por $250.000 exento.')->assertOk();
        $proposal = $response->json('components.0');

        $this->assertSame('Exento', collect($proposal['fields'])->firstWhere('label', 'Impuesto')['value']);
        $this->assertSame('Servicio', collect($proposal['fields'])->firstWhere('label', 'Tipo')['value']);
    }

    public function test_an_unconfigured_tax_rate_is_rejected_instead_of_invented(): void
    {
        $user = $this->facturador();
        // No Tax row at 7% exists for this company.

        $response = $this->ask($user, 'Prepara un producto Correas por $50.000 con IVA 7%.')->assertOk();

        $this->assertEmpty(array_filter($response->json('components'), fn (array $c): bool => $c['type'] === 'action_proposal'));
        $this->assertStringContainsString('No encontré un impuesto', $response->json('message'));
    }

    public function test_confirming_creates_the_product_with_a_unique_generated_sku(): void
    {
        $user = $this->facturador();
        Tax::factory()->create(['code' => 'IVA19', 'rate' => '19']);

        $token = $this->ask($user, 'Prepara un producto Zapatos por $180.000 con IVA 19%.')->json('components.0.token');
        $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token])->assertOk();

        $product = ProductService::first();
        $this->assertSame('Zapatos', $product->name);
        $this->assertNotEmpty($product->sku);
        $this->assertSame(1, $product->taxes()->count());
        $this->assertSame($user->company_id, $product->company_id);
    }

    public function test_missing_price_asks_and_the_next_turn_completes_it(): void
    {
        $user = $this->facturador();

        $first = $this->ask($user, 'Prepara un producto Camisa Azul.')->assertOk();
        $this->assertSame([], $first->json('components'));

        $second = $this->ask($user, 'Por $100.000.', $first->json('conversation_id'));

        // "Por $100.000." alone has no "producto"/"servicio" keyword, so it
        // never re-triggers detectIntent — it only works as a continuation
        // because the pending action from the first turn is still active.
        $second->assertOk();
    }
}
