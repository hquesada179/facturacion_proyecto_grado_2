<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Bridge (VYA integration, Fase 1)
    |--------------------------------------------------------------------------
    |
    | Nothing in application code should read these via env() directly —
    | always go through config('whatsapp.*'), same convention as
    | config('ai.*'). .env.example only ever lists an empty placeholder
    | for the secret; .env (gitignored) holds the real one.
    |
    | WHATSAPP_BRIDGE_ENABLED is a hard kill switch: when false (the
    | default), VerifyWhatsappBridgeSignature rejects every request
    | before even checking the signature, and the Settings UI shows the
    | integration as unavailable. Everything else in Fiscora keeps
    | working regardless of this flag — see the Fase 1 report.
    |
    */

    'enabled' => (bool) env('WHATSAPP_BRIDGE_ENABLED', false),

    'bridge_secret' => env('WHATSAPP_BRIDGE_SECRET'),

    // Seconds of clock drift tolerated between X-Fiscora-Timestamp and
    // the server's own time, in either direction, before a signed
    // request is rejected as too old/too far in the future.
    'max_skew' => (int) env('WHATSAPP_BRIDGE_MAX_SKEW', 300),

];
