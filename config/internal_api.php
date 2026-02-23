<?php

return [
    'username' => env('INTERNAL_API_USERNAME', 'joberto24'),
    'password' => env('INTERNAL_API_PASSWORD', 'jobertpass24'),
    'token_ttl_minutes' => (int) env('INTERNAL_API_TOKEN_TTL_MINUTES', 1440),
];
