<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Whatsapp\WhatsappLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * "Configuración > Integraciones > WhatsApp" — gated by `use-assistant`
 * (not `manage-company`): this links the AUTHENTICATED USER's own
 * WhatsApp to the assistant, the same ability every role that can
 * already use the web assistant has, not an administrative setting.
 */
class WhatsappIntegrationController extends Controller
{
    public function __construct(private readonly WhatsappLinkService $links) {}

    public function show(Request $request): View
    {
        Gate::authorize('use-assistant');

        $user = $request->user();
        $connection = $this->links->activeConnectionForUser($user);

        return view('settings.integrations.whatsapp', [
            'connection' => $connection,
            'bridgeEnabled' => (bool) config('whatsapp.enabled'),
            'pendingCode' => $request->session()->get('whatsapp_link_code'),
        ]);
    }

    public function generateCode(Request $request): RedirectResponse
    {
        Gate::authorize('use-assistant');

        $user = $request->user();

        if ($this->links->activeConnectionForUser($user) !== null) {
            return redirect()->route('settings.integrations.whatsapp')
                ->with('error', 'Ya tienes un WhatsApp vinculado. Desvincúlalo antes de generar un código nuevo.');
        }

        $result = $this->links->generate($user);

        return redirect()->route('settings.integrations.whatsapp')
            ->with('whatsapp_link_code', [
                'code' => $result['code'],
                'expires_at' => $result['expires_at']->toIso8601String(),
            ]);
    }

    public function revoke(Request $request): RedirectResponse
    {
        Gate::authorize('use-assistant');

        $user = $request->user();
        $connection = $this->links->activeConnectionForUser($user);

        if ($connection === null) {
            return redirect()->route('settings.integrations.whatsapp');
        }

        $this->links->revoke($user, $connection);

        return redirect()->route('settings.integrations.whatsapp')
            ->with('status', 'WhatsApp desvinculado correctamente.');
    }
}
