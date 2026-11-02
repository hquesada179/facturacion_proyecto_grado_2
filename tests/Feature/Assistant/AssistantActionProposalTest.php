<?php

namespace Tests\Feature\Assistant;

use App\Models\AssistantActionProposal;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ProductService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * IA 2.0-D: the generic proposal/confirm mechanics shared by every
 * action (create_invoice_draft/create_customer/create_product/
 * create_credit_note_draft and the carried-over issue_invoice) — see
 * ActionProposalStore. Uses create_invoice_draft as the vehicle since
 * it is the richest payload, but nothing here is invoice-specific.
 */
class AssistantActionProposalTest extends TestCase
{
    use RefreshDatabase;

    private function facturador(?Company $company = null): User
    {
        return User::factory()->for($company ?? Company::factory())->facturador()->create();
    }

    private function seedKataAndCamisas(User $user): ProductService
    {
        Customer::factory()->for($user->company)->create(['name' => 'Kata', 'status' => 'active']);

        return ProductService::factory()->for($user->company)->create(['name' => 'Camisas', 'price' => '50000']);
    }

    private function propose(User $user, string $message = 'Prepárame una factura para Kata con 2 camisas.'): TestResponse
    {
        return $this->actingAs($user)->postJson(route('assistant.message'), ['message' => $message, 'screen' => 'dashboard']);
    }

    public function test_proposing_an_action_never_writes_the_resulting_record(): void
    {
        $user = $this->facturador();
        $this->seedKataAndCamisas($user);

        $response = $this->propose($user)->assertOk();

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
        $this->assertDatabaseCount('assistant_action_proposals', 1);
        $this->assertSame('pending', AssistantActionProposal::first()->status);
        $this->assertTrue((bool) $response->json('requires_confirmation'));
    }

    public function test_the_raw_token_is_never_persisted_only_its_hash(): void
    {
        $user = $this->facturador();
        $this->seedKataAndCamisas($user);

        $response = $this->propose($user)->assertOk();
        $token = $response->json('components.0.token');

        $proposal = AssistantActionProposal::first();
        $this->assertNotSame($token, $proposal->token_hash);
        $this->assertSame(hash('sha256', $token), $proposal->token_hash);
    }

    public function test_the_proposal_is_bound_to_the_proposing_user_and_company(): void
    {
        $user = $this->facturador();
        $this->seedKataAndCamisas($user);

        $this->propose($user)->assertOk();

        $proposal = AssistantActionProposal::first();
        $this->assertSame($user->id, $proposal->user_id);
        $this->assertSame($user->company_id, $proposal->company_id);
    }

    public function test_confirming_creates_exactly_one_record(): void
    {
        $user = $this->facturador();
        $this->seedKataAndCamisas($user);

        $token = $this->propose($user)->json('components.0.token');
        $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token])->assertOk();

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_a_double_confirmation_never_duplicates_the_record(): void
    {
        $user = $this->facturador();
        $this->seedKataAndCamisas($user);

        $token = $this->propose($user)->json('components.0.token');

        $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token])->assertOk();
        $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token])->assertStatus(422);

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_a_cancelled_proposal_can_never_be_confirmed(): void
    {
        $user = $this->facturador();
        $this->seedKataAndCamisas($user);

        $token = $this->propose($user)->json('components.0.token');

        $this->actingAs($user)->postJson(route('assistant.cancel'), ['token' => $token])->assertOk();
        $this->assertSame('cancelled', AssistantActionProposal::first()->status);

        $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token])->assertStatus(422);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_a_cancelled_proposal_can_never_be_cancelled_again(): void
    {
        $user = $this->facturador();
        $this->seedKataAndCamisas($user);

        $token = $this->propose($user)->json('components.0.token');

        $this->actingAs($user)->postJson(route('assistant.cancel'), ['token' => $token])->assertOk();
        $this->actingAs($user)->postJson(route('assistant.cancel'), ['token' => $token])->assertStatus(422);
    }

    public function test_an_expired_proposal_cannot_be_confirmed_and_is_marked_expired(): void
    {
        $user = $this->facturador();
        $this->seedKataAndCamisas($user);

        $token = $this->propose($user)->json('components.0.token');
        AssistantActionProposal::query()->update(['expires_at' => now()->subMinute()]);

        $response = $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token]);
        $response->assertStatus(422);
        $this->assertStringContainsString('expiró', $response->json('message'));

        $this->assertSame('expired', AssistantActionProposal::first()->status);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_a_proposal_belonging_to_another_user_cannot_be_confirmed(): void
    {
        $owner = $this->facturador();
        $this->seedKataAndCamisas($owner);
        $token = $this->propose($owner)->json('components.0.token');

        $otherUser = $this->facturador($owner->company);
        $this->actingAs($otherUser)->postJson(route('assistant.confirm'), ['token' => $token])->assertStatus(422);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_a_proposal_belonging_to_another_company_cannot_be_confirmed(): void
    {
        $owner = $this->facturador();
        $this->seedKataAndCamisas($owner);
        $token = $this->propose($owner)->json('components.0.token');

        $otherCompanyUser = $this->facturador();
        $this->actingAs($otherCompanyUser)->postJson(route('assistant.confirm'), ['token' => $token])->assertStatus(422);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_an_unknown_token_is_rejected_without_leaking_anything(): void
    {
        $user = $this->facturador();

        $response = $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => str_repeat('z', 48)]);
        $response->assertStatus(422);
    }

    public function test_a_material_price_change_blocks_confirmation_and_marks_the_proposal_failed(): void
    {
        $user = $this->facturador();
        $product = $this->seedKataAndCamisas($user);

        $token = $this->propose($user)->json('components.0.token');

        $product->update(['price' => '120000']);

        $response = $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token]);
        $response->assertStatus(422);
        $this->assertStringContainsString('información cambió', $response->json('message'));

        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame('failed', AssistantActionProposal::first()->status);
    }

    public function test_extra_fields_in_the_confirm_request_are_ignored_entirely(): void
    {
        // Simulated DevTools tampering: company_id/total/price/quantity
        // sent alongside the token must have zero effect — confirm only
        // ever reads `token`.
        $user = $this->facturador();
        $this->seedKataAndCamisas($user);

        $token = $this->propose($user)->json('components.0.token');

        $this->actingAs($user)->postJson(route('assistant.confirm'), [
            'token' => $token,
            'company_id' => 999999,
            'total' => '1',
            'price' => '1',
            'quantity' => '999',
            'tax' => '0',
        ])->assertOk();

        $invoice = \App\Models\Invoice::first();
        $this->assertSame($user->company_id, $invoice->company_id);
        $this->assertSame(2, (int) $invoice->items->first()->quantity);
        $this->assertSame('50000.00', (string) $invoice->items->first()->unit_price);
    }

    public function test_every_action_audit_event_is_recorded_without_storing_the_raw_token(): void
    {
        $user = $this->facturador();
        $this->seedKataAndCamisas($user);

        $token = $this->propose($user)->json('components.0.token');
        $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token])->assertOk();

        $events = \App\Models\AssistantActionEvent::query()->pluck('event')->all();
        $this->assertContains('assistant_action_proposed', $events);
        $this->assertContains('assistant_action_confirmed', $events);

        $dump = \App\Models\AssistantActionEvent::query()->get()->toJson();
        $this->assertStringNotContainsString($token, $dump);
    }

    public function test_cancelling_and_expiring_are_both_audited(): void
    {
        $user = $this->facturador();
        $this->seedKataAndCamisas($user);

        $token = $this->propose($user)->json('components.0.token');
        $this->actingAs($user)->postJson(route('assistant.cancel'), ['token' => $token])->assertOk();

        $this->assertContains('assistant_action_cancelled', \App\Models\AssistantActionEvent::query()->pluck('event')->all());

        $token2 = $this->propose($user)->json('components.0.token');
        AssistantActionProposal::query()->where('token_hash', hash('sha256', $token2))->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($user)->postJson(route('assistant.confirm'), ['token' => $token2])->assertStatus(422);

        $this->assertContains('assistant_action_expired', \App\Models\AssistantActionEvent::query()->pluck('event')->all());
    }
}
