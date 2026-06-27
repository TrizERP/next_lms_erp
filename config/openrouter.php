<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenRouter Configuration
    |--------------------------------------------------------------------------
    |
    | Used by PAL intelligence layer for AI-powered diagnostics and content.
    | Set OPENROUTER_API_KEY in your .env file.
    |
    */

    'api_key' => env('OPENROUTER_API_KEY'),

    'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),

    'model' => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),

    'headers' => [
        'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
        'Content-Type'  => 'application/json',
        'HTTP-Referer'  => env('APP_URL', 'http://localhost'),
        'X-Title'       => env('APP_NAME', 'LMS'),
    ],
];