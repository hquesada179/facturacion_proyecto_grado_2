<?php

namespace Tests\Feature\Assistant;

use App\Models\AssistantMetric;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantMetricsAndFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function facturador(?Company $company = null): User
    {
        return User::factory()->for($company ?? Company::factory())->facturador()->create();
    }

    public function test_a_metric_row_is_recorded_for_every_interaction(): void
    {
        $user = $this->facturador();

        $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Hola',
            'screen' => 'dashboard',
        ])->assertOk();

        $this->assertDatabaseHas('assistant_metrics', [
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'screen' => 'dashboard',
            'status' => 'resolved',
        ]);

        $metric = AssistantMetric::where('user_id', $user->id)->firstOrFail();
        $this->assertGreaterThanOrEqual(0, $metric->latency_ms);
    }

    public function test_the_tool_used_is_recorded_on_the_metric(): void
    {
        $user = $this->facturador();

        $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Busca el cliente Andina',
            'screen' => 'customers.index',
        ])->assertOk();

        $metric = AssistantMetric::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(['find_customer'], $metric->tool_names);
    }

    public function test_the_internal_reasoning_of_the_model_is_never_persisted(): void
    {
        $user = $this->facturador();

        $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Hola',
            'screen' => 'dashboard',
        ])->assertOk();

        $metric = AssistantMetric::where('user_id', $user->id)->firstOrFail();
        $this->assertArrayNotHasKey('reasoning', $metric->getAttributes());
        $this->assertArrayNotHasKey('raw_response', $metric->getAttributes());
    }

    public function test_a_user_can_submit_feedback_on_an_assistant_reply(): void
    {
        $user = $this->facturador();

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Hola',
            'screen' => 'dashboard',
        ]);
        $messageId = $response->json('message_id');

        $this->actingAs($user)->postJson(route('assistant.feedback', $messageId), [
            'feedback' => 'useful',
        ])->assertOk();

        $this->assertDatabaseHas('assistant_messages', [
            'id' => $messageId,
            'feedback' => 'useful',
        ]);
    }

    public function test_feedback_rejects_values_outside_the_allowed_set(): void
    {
        $user = $this->facturador();

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'Hola',
            'screen' => 'dashboard',
        ]);
        $messageId = $response->json('message_id');

        $this->actingAs($user)->postJson(route('assistant.feedback', $messageId), [
            'feedback' => 'maybe',
        ])->assertStatus(422);
    }

    public function test_a_user_cannot_give_feedback_on_someone_elses_conversation(): void
    {
        $userA = $this->facturador();
        $userB = $this->facturador();

        $response = $this->actingAs($userA)->postJson(route('assistant.message'), [
            'message' => 'Hola',
            'screen' => 'dashboard',
        ]);
        $messageId = $response->json('message_id');

        $this->actingAs($userB)->postJson(route('assistant.feedback', $messageId), [
            'feedback' => 'not_useful',
        ])->assertForbidden();
    }
}
