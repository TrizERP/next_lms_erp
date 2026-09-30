<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['*'],

    'allowed_methods' => ['*'],

    // Comma-separated list of browser origins allowed to call the API, e.g.
    // CORS_ALLOWED_ORIGINS=https://lms.example.in,https://erp.triz.co.in
    // Defaults to '*' (unchanged behaviour) until the deployment sets it.
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '*'))))),

    /*
    | Vercel preview deployments get a generated hostname per branch/commit, so
    | they cannot be enumerated in the list above. Scoped to the project's own
    | preview namespace rather than all of vercel.app.
    */
    'allowed_origins_patterns' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', '#^https://lms-k12-[a-z0-9-]+\.vercel\.app$#'))
    ))),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
