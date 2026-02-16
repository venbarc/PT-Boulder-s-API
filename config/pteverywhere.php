<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PtEverywhere API Configuration
    |--------------------------------------------------------------------------
    */

    'base_url' => env('PTE_API_BASE_URL', 'https://openapi.pteverywhere.com/api/v2'),

    'username' => env('PTE_USERNAME'),

    'password' => env('PTE_PASSWORD'),

    // Webhook secret key for decrypting webhook payloads
    'webhook_secret' => env('PTE_WEBHOOK_SECRET'),

    // Token cache duration in minutes (Cognito tokens typically last 60 min)
    'token_ttl' => env('PTE_TOKEN_TTL', 55),

    // TLS verification settings for API requests.
    // Set PTE_API_CA_BUNDLE to a CA bundle file path (common for Windows PHP/cURL setups).
    'ssl_verify' => env('PTE_API_SSL_VERIFY', true),
    'ca_bundle' => env('PTE_API_CA_BUNDLE'),

    // Empty string disables inherited HTTP(S)_PROXY env vars.
    'proxy' => env('PTE_API_PROXY', ''),

];
