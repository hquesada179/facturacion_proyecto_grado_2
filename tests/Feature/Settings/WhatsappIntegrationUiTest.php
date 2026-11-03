<?php

namespace Tests\Feature\Settings;

use App\Models\Company;
use App\Models\User;
use App\Services\Whatsapp\WhatsappLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappIntegrationUiTest extends TestCase
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

    public function test_the_user_sees_the_unlinked_state(): void
    {
        $user = $this->admin();

        $response = $this->actingAs($user)->get(route('settings.integrations.whatsapp'));

        $response->assertOk();
        $response->assertSee('WhatsApp');
        $response->assertSee('Conecta tu cuenta para consultar Fiscora');
        $response->assertSee('Vincular WhatsApp');
        $response->assertDontSee('WhatsApp conectado');
    }

    public function test_the_user_can_generate_a_code(): void
    {
        $user = $this->admin();

        $response = $this->actingAs($user)->post(route('settings.integrations.whatsapp.link'));
        $response->assertRedirect(route('settings.integrations.whatsapp'));

        $page = $this->actingAs($user)->get(route('settings.integrations.whatsapp'));

        $page->assertOk();
        $page->assertSee('Envía este mensaje al WhatsApp de Fiscora');
        $page->assertSee('VINCULAR FISCORA-', false);
    }

    public function test_a_linked_user_sees_the_connected_state(): void
    {
        $user = $this->admin();
        $code = $this->links()->generate($user)['code'];
        $this->links()->consume('573001234567', null, str_replace('FISCORA-', '', $code));

        $response = $this->actingAs($user)->get(route('settings.integrations.whatsapp'));

        $response->assertOk();
        $response->assertSee('WhatsApp conectado');
        $response->assertSee('Desvincular WhatsApp');
        $response->assertSee($user->company->name);
    }

    public function test_the_number_is_shown_masked_not_in_full(): void
    {
        $user = $this->admin();
        $code = $this->links()->generate($user)['code'];
        $this->links()->consume('573001234567', null, str_replace('FISCORA-', '', $code));

        $response = $this->actingAs($user)->get(route('settings.integrations.whatsapp'));

        $response->assertOk();
        $response->assertSee('+57 ••• ••• 4567');
        $response->assertDontSee('573001234567');
    }

    public function test_unlinking_requires_explicit_human_confirmation_in_the_ui(): void
    {
        $user = $this->admin();
        $code = $this->links()->generate($user)['code'];
        $this->links()->consume('573001234567', null, str_replace('FISCORA-', '', $code));

        $response = $this->actingAs($user)->get(route('settings.integrations.whatsapp'));

        $response->assertOk();
        // The "Desvincular" action is a real POST gated by a client-side
        // confirm() prompt — not a bare link that fires on click/GET.
        $response->assertSee('onsubmit="return confirm(', false);
        $response->assertSee('¿Desvincular este WhatsApp de Fiscora?', false);
    }

    public function test_revoke_actually_unlinks_and_preserves_history(): void
    {
        $user = $this->admin();
        $code = $this->links()->generate($user)['code'];
        $outcome = $this->links()->consume('573001234567', null, str_replace('FISCORA-', '', $code));

        $this->actingAs($user)->post(route('settings.integrations.whatsapp.unlink'))
            ->assertRedirect(route('settings.integrations.whatsapp'));

        $connection = $outcome['connection']->refresh();
        $this->assertFalse($connection->isActive());
        $this->assertNotNull($connection->wa_id, 'El historial de la conexión debe conservarse, no borrarse.');

        $response = $this->actingAs($user)->get(route('settings.integrations.whatsapp'));
        $response->assertOk();
        $response->assertDontSee('WhatsApp conectado');
    }

    public function test_a_user_cannot_revoke_another_companys_connection(): void
    {
        $userA = $this->admin();
        $codeA = $this->links()->generate($userA)['code'];
        $outcomeA = $this->links()->consume('573001234567', null, str_replace('FISCORA-', '', $codeA));

        $userB = $this->admin();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->links()->revoke($userB, $outcomeA['connection']);
    }

    public function test_the_whatsapp_settings_tab_is_reachable_from_configuracion(): void
    {
        $user = $this->admin();

        $response = $this->actingAs($user)->get(route('settings.company'));

        $response->assertOk();
        $response->assertSee('Integraciones');
        $response->assertSee(route('settings.integrations.whatsapp'), false);
    }
}
