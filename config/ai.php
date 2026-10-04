<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Assistant Provider
    |--------------------------------------------------------------------------
    |
    | Central configuration for Fiscora's AI assistant. Nothing in
    | application code should read these values via env() directly —
    | always go through config('ai.*') so behaviour stays testable and
    | credentials stay out of version control (.env is gitignored;
    | .env.example only ever lists empty/non-sensitive placeholders).
    |
    | AI_ENABLED acts as a hard kill switch: when false (the default),
    | the assistant always uses the deterministic LocalFallbackProvider
    | regardless of AI_PROVIDER, and no external HTTP call is ever made.
    |
    */

    'enabled' => (bool) env('AI_ENABLED', false),

    'provider' => env('AI_PROVIDER', 'local'),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 30),
    ],

];
