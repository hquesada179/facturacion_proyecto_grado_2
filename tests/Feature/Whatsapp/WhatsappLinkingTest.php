<?php

namespace Tests\Feature\Whatsapp;

use App\Models\Company;
use App\Models\User;
use App\Models\WhatsappConnection;
use App\Models\WhatsappLinkCode;
use App\Services\Whatsapp\WhatsappLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WhatsappLinkingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(?Company $company = null): User
    {
        return User::factory()->for($company ?? Company::factory())->administrador()->create();
    }

    private function links(): WhatsappLinkService
    {
        return app(WhatsappLinkService::class);
    }

    public function test_an_authenticated_user_can_generate_a_link_code(): void
    {
        $user = $this->admin();

        $response = $this->actingAs($user)->post(route('settings.integrations.whatsapp.link'));

        $response->assertRedirect(route('settings.integrations.whatsapp'));
        $response->assertSessionHas('whatsapp_link_code');

        $flashed = $response->getSession()->get('whatsapp_link_code');
        $this->assertStringStartsWith('FISCORA-', $flashed['code']);
        $this->assertSame(1, WhatsappLinkCode::where('user_id', $user->id)->count());
    }

    public function test_the_generated_code_has_an_expiration(): void
    {
        $user = $this->admin();

        $result = $this->links()->generate($user);

        $record = WhatsappLinkCode::where('user_id', $user->id)->first();
        $this->assertFalse($record->expires_at->isPast());
        // Compare at second precision: the DB round-trip drops
        // microseconds, so an exact equalTo() against the in-memory
        // Carbon instance would be comparing more precision than the
        // column actually stores.
        $this->assertSame($result['expires_at']->toDateTimeString(), $record->expires_at->toDateTimeString());
        $this->assertSame(
            WhatsappLinkService::EXPIRES_IN_MINUTES,
            (int) round($record->created_at->diffInMinutes($record->expires_at))
        );
    }

    public function test_the_code_is_never_persisted_in_plain_text(): void
    {
        $user = $this->admin();

        $result = $this->links()->generate($user);
        $plain = str_replace('FISCORA-', '', $result['code']);

        $record = WhatsappLinkCode::where('user_id', $user->id)->first();

        $this->assertDatabaseMissing('whatsapp_link_codes', ['code_hash' => $plain]);
        $this->assertDatabaseMissing('whatsapp_link_codes', ['lookup_hash' => $plain]);
        $this->assertNotSame($plain, $record->getAttributes()['code_hash']);
        $this->assertTrue(Hash::check($plain, $record->getAttributes()['code_hash']));
    }

    public function test_the_correct_code_links_the_wa_id(): void
    {
        $user = $this->admin();
        $result = $this->links()->generate($user);
        $plain = str_replace('FISCORA-', '', $result['code']);

        $outcome = $this->links()->consume('573001234567', 'pnid-1', $plain);

        $this->assertTrue($outcome['success']);
        $this->assertSame($user->id, $outcome['connection']->user_id);
        $this->assertSame($user->company_id, $outcome['connection']->company_id);
        $this->assertSame('573001234567', $outcome['connection']->wa_id);
        $this->assertNotNull($outcome['connection']->verified_at);
    }

    public function test_an_incorrect_code_does_not_link(): void
    {
        $user = $this->admin();
        $this->links()->generate($user);

        $outcome = $this->links()->consume('573001234567', null, 'WRONGC');

        $this->assertFalse($outcome['success']);
        $this->assertSame(0, WhatsappConnection::withoutGlobalScopes()->count());
    }

    public function test_an_expired_code_does_not_link(): void
    {
        $user = $this->admin();
        $result = $this->links()->generate($user);
        $plain = str_replace('FISCORA-', '', $result['code']);

        WhatsappLinkCode::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);

        $outcome = $this->links()->consume('573001234567', null, $plain);

        $this->assertFalse($outcome['success']);
    }

    public function test_a_used_code_cannot_be_reused(): void
    {
        $user = $this->admin();
        $result = $this->links()->generate($user);
        $plain = str_replace('FISCORA-', '', $result['code']);

        $first = $this->links()->consume('573001234567', null, $plain);
        $this->assertTrue($first['success']);

        $second = $this->links()->consume('573009999999', null, $plain);
        $this->assertFalse($second['success']);
        $this->assertSame(1, WhatsappConnection::withoutGlobalScopes()->count());
    }

    public function test_regenerating_a_code_invalidates_the_previous_one(): void
    {
        $user = $this->admin();
        $old = $this->links()->generate($user);
        $oldPlain = str_replace('FISCORA-', '', $old['code']);

        $this->links()->generate($user);

        $outcome = $this->links()->consume('573001234567', null, $oldPlain);

        $this->assertFalse($outcome['success']);
        $this->assertSame(1, WhatsappLinkCode::where('user_id', $user->id)->count());
    }

    public function test_another_user_never_sees_a_different_users_pending_code_or_connection(): void
    {
        $userA = $this->admin();
        $userB = $this->admin();

        // Baseline, in a browser session that has never touched A's
        // flow at all: B sees the plain "no vinculado" state.
        $pageAsB = $this->actingAs($userB)->get(route('settings.integrations.whatsapp'));
        $pageAsB->assertOk();
        $pageAsB->assertDontSee('FISCORA-');
        $pageAsB->assertDontSee('WhatsApp conectado');

        $plainCode = $this->links()->generate($userA)['code'];
        $outcome = $this->links()->consume('573001234567', null, str_replace('FISCORA-', '', $plainCode));
        $this->assertTrue($outcome['success']);

        // Server-side: B's own scoped lookup only ever finds B's
        // connection/code — never A's, regardless of what exists in the
        // table for other users.
        $this->assertNull($this->links()->activeConnectionForUser($userB));
        $this->assertSame(0, WhatsappLinkCode::where('user_id', $userB->id)->count());

        $pageAsBAgain = $this->actingAs($userB)->get(route('settings.integrations.whatsapp'));
        $pageAsBAgain->assertOk();
        $pageAsBAgain->assertDontSee('WhatsApp conectado');
        $pageAsBAgain->assertDontSee($userA->company->name);
    }

    public function test_the_same_wa_id_can_never_be_actively_linked_to_two_companies_at_once(): void
    {
        $userA = $this->admin();
        $userB = $this->admin();
        $waId = '573001234567';

        $codeA = $this->links()->generate($userA);
        $outcomeA = $this->links()->consume($waId, null, str_replace('FISCORA-', '', $codeA['code']));
        $this->assertTrue($outcomeA['success']);

        $codeB = $this->links()->generate($userB);
        $outcomeB = $this->links()->consume($waId, null, str_replace('FISCORA-', '', $codeB['code']));
        $this->assertTrue($outcomeB['success']);

        $connectionA = $outcomeA['connection']->refresh();
        $connectionB = $outcomeB['connection']->refresh();

        $this->assertFalse($connectionA->isActive(), 'La conexión de la empresa A debió quedar revocada.');
        $this->assertTrue($connectionB->isActive());
        $this->assertSame(
            1,
            WhatsappConnection::withoutGlobalScopes()->where('active_wa_id', $waId)->count(),
            'Solo una empresa puede tener este WhatsApp activo a la vez.'
        );
    }
}
