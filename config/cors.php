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

    /*
    | Origins allowed to drive this API from a browser.
    |
    | This was previously ['*'], which meant any page on the internet could make
    | a visitor's browser call the API - including the billable LLM endpoints
    | (intelligence/questions/generate, lesson-intelligence/micro-plan/*). Those
    | endpoints are now behind `api.session`, but a wildcard here is still the
    | wrong default: it lets an attacker's page read any response the browser is
    | able to obtain.
    |
    | Override per environment with a comma-separated CORS_ALLOWED_ORIGINS.
    */
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', implode(',', [
            'https://lms-k12.vercel.app',
            'https://dev.triz.co.in',
            'http://localhost:3000',
            'http://127.0.0.1:3000',
        ])))
    ))),

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
