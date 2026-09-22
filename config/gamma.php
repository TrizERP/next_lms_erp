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

    /*
    | Image generator Gamma uses for the illustrations on each card.
    |
    | This was hardcoded as 'imagen-4-pro' at three call sites
    | (contentController::storeGammaContent, GammaService, OpenAIService).
    | Gamma has since retired that model and now rejects it outright with
    | "Input validation errors: imageModel must be one of: ..." and HTTP 400 -
    | which failed EVERY presentation generation in the app, classroom decks
    | and teacher training decks alike, before a single credit was spent.
    |
    | Pulled into config so the next time Gamma rotates its model list this is
    | one env var rather than three edits. The accepted list is Gamma's to
    | change, so it is deliberately NOT mirrored here as a whitelist - a stale
    | local copy would reintroduce exactly this failure.
    |
    | This is also the main cost lever on a bulk run: the flash/mini image
    | models are markedly cheaper per card than the pro ones.
    */
    'image_model' => env('GAMMA_IMAGE_MODEL', 'gemini-3-pro-image'),

    // Whitelisted by contentController.php:1613 today. Kept here so the list has one home.
    'themes' => ['simple', 'minimal', 'corporate', 'creative', 'bold', 'elegant', 'modern'],

    'default_theme' => env('GAMMA_THEME_ID'),

    'request_timeout' => env('GAMMA_REQUEST_TIMEOUT', 120),
];
