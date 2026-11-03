<?php

namespace App\Services\Whatsapp;

/**
 * Meta's own wa_id is already digits-only (country code + number, no
 * "+", no spaces), but anything reaching this from a human-entered or
 * loosely-formatted source ("+57 300 123 4567") needs to collapse to
 * that exact same canonical form before ever being compared or stored
 * — otherwise "573001234567" and "+57 300 123 4567" would look like two
 * different numbers and the uniqueness guarantee on
 * whatsapp_connections.active_wa_id would stop meaning anything.
 */
class WhatsappNumberNormalizer
{
    public static function normalize(string $waId): string
    {
        return preg_replace('/\D/', '', $waId) ?? '';
    }
}
