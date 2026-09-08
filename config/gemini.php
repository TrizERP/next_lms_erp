<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Gemini API Key
    |--------------------------------------------------------------------------
    |
    | Here you may specify your Gemini API Key and organization. This will be
    | used to authenticate with the Gemini API - you can find your API key
    | on Google AI Studio, at https://makersuite.google.com.
    */

    'api_key' => env('GEMINI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Gemini Base URL
    |--------------------------------------------------------------------------
    |
    | If you need a specific base URL for the Gemini API, you can provide it here.
    | Otherwise, leave empty to use the default value.
    */
    'base_url' => env('GEMINI_BASE_URL'),

    /*
    | The model id. Added 2026-09-08: contentController::storeGammaContent reads this via
    | env('GEMINI_MODEL') at request time (:1696), which returns null under `config:cache`.
    | Declared here so the content-authoring gateway resolves it through config() instead.
    */
    'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    // Default corrected 2026-09-08: it was 'gemini-2.0-flash', while every other call
    // site in this repo defaults to 2.5 (contentController.php:1696,
    // AiSopGenerationController.php:136, Homework/GeminiClient.php:23). GEMINI_MODEL is
    // not set in .env, so the wrong default meant the content-authoring gateway would
    // have called a DIFFERENT model than the legacy path it claims parity with.

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout may be used to specify the maximum number of seconds to wait
    | for a response. By default, the client will time out after 30 seconds.
    */

    'request_timeout' => env('GEMINI_REQUEST_TIMEOUT', 30),
];
