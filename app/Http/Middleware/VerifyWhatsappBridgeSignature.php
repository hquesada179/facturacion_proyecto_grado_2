<?php

namespace App\Http\Middleware;

use App\Services\Whatsapp\WhatsappBridgeAuditLogger;
use App\Services\Whatsapp\WhatsappHmacSignatureVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Machine-to-machine authentication for the VYA bridge — no session, no
 * CSRF, no login. Rejects before the controller ever runs: feature
 * disabled, signature headers missing, timestamp out of range, or
 * signature mismatch. Never logs the secret, the signature, or the
 * request body — only a short rejection reason (see
 * WhatsappHmacSignatureVerifier::verify()).
 */
class VerifyWhatsappBridgeSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $audit = app(WhatsappBridgeAuditLogger::class);

        if (! config('whatsapp.enabled')) {
            $audit->bridgeDisabled();

            return response()->json(['handled' => false, 'error' => 'bridge_disabled'], 503);
        }

        $verifier = new WhatsappHmacSignatureVerifier(
            (string) config('whatsapp.bridge_secret'),
            (int) config('whatsapp.max_skew'),
        );

        $rejection = $verifier->verify(
            $request->header('X-Fiscora-Timestamp'),
            $request->header('X-Fiscora-Signature'),
            $request->getContent(),
        );

        if ($rejection !== null) {
            $audit->signatureRejected($rejection);

            return response()->json(['handled' => false, 'error' => 'unauthorized'], 401);
        }

        return $next($request);
    }
}
