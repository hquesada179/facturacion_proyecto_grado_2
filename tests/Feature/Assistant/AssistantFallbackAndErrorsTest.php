<?php

namespace Tests\Feature\Assistant;

use App\Models\Company;
use App\Models\User;
use App\Services\Assistant\AssistantResponse;
use App\Services\Assistant\Contracts\AiProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AssistantFallbackAndErrorsTest extends TestCase
{
    use RefreshDatabase;

    private function facturador(): User
    {
        return User::factory()->for(Company::factory())->facturador()->create();
    }

    public function test_local_fallback_answers_without_any_external_credentials(): void
    {
        config([
            'ai.enabled' => false,
            'ai.provider' => 'local',
            'ai.openai.api_key' => '',
            'ai.openai.model' => '',
        ]);

        $user = $this->facturador();

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Hola, ¿qué puedes hacer?',
            'screen' => 'dashboard',
        ])->assertOk();

        $this->assertStringContainsString('El proveedor de IA externo no está configurado', $response->json('message'));
        $this->assertDatabaseHas('assistant_metrics', ['provider' => 'local_fallback', 'status' => 'resolved']);
    }

    public function test_the_invoicing_system_keeps_working_when_the_external_provider_fails(): void
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
        $this->assertStringContainsString('continúan funcionando normalmente', $response->json('message'));
        $this->assertDatabaseHas('assistant_metrics', ['status' => 'error']);

        // Core invoicing endpoints must stay reachable regardless of the assistant's health.
        $this->actingAs($user)->get('/clientes')->assertOk();
    }

    public function test_a_slow_or_unreachable_external_provider_never_breaks_the_response(): void
    {
        $user = $this->facturador();

        $this->app->bind(AiProviderInterface::class, fn () => new class implements AiProviderInterface
        {
            public function generate(array $context, string $message, array $toolResults = []): AssistantResponse
            {
                throw new RuntimeException('Timeout');
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

        $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Hola',
            'screen' => 'dashboard',
        ])->assertOk()->assertJsonStructure(['message', 'suggestions', 'actions', 'requires_confirmation', 'conversation_id', 'message_id']);
    }
}
