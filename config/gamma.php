<?php

/*
|--------------------------------------------------------------------------
| Gamma presentation provider
|--------------------------------------------------------------------------
|
| Mirrors config/gemini.php, and exists for the same reason: GAMMA_API_KEY and
| GAMMA_BASE_URL are read via env() at REQUEST TIME today - GammaService.php:15-16, and
| again inline in contentController::storeGammaContent at :1893 and :1926.
|
| env() returns null once `php artisan config:cache` has run, which is the standard
| production deploy step. So a working deployment silently turns into a failed
| generation, with the key simply absent. Declaring the values here lets the content
| authoring gateway resolve them through config(), which is cache-safe.
|
| GammaService itself is left untouched - changing how a live service reads its key is
| not in scope for this phase.
|
*/

return [

    'api_key' => env('GAMMA_API_KEY'),

    'base_url' => env('GAMMA_BASE_URL', 'https://public-api.gamma.app/v1.0/'),

    // Gamma bills per generation rather than per token, so this is recorded for the
    // audit trail rather than used for costing.
    'model' => env('GAMMA_MODEL', 'gamma-generate'),

    // Whitelisted by contentController.php:1613 today. Kept here so the list has one home.
    'themes' => ['simple', 'minimal', 'corporate', 'creative', 'bold', 'elegant', 'modern'],

    'default_theme' => env('GAMMA_THEME_ID'),

    'request_timeout' => env('GAMMA_REQUEST_TIMEOUT', 120),
];
