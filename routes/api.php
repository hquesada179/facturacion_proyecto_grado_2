<?php

use App\Http\Controllers\Integrations\WhatsappBridgeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (stateless, no session/CSRF)
|--------------------------------------------------------------------------
|
| Fase 1: the VYA WhatsApp bridge only. Authenticated machine-to-machine
| via HMAC-SHA256 (VerifyWhatsappBridgeSignature) — never session auth,
| never a login. The web assistant's own authenticated routes under
| /asistente/* in routes/web.php are completely untouched by this file.
|
*/

Route::post('/integrations/whatsapp/bridge', [WhatsappBridgeController::class, 'handle'])
    ->middleware(['whatsapp.bridge.signature', 'throttle:whatsapp-bridge'])
    ->name('api.integrations.whatsapp.bridge');
