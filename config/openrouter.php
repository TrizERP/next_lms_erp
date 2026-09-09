<?php

/*
|--------------------------------------------------------------------------
| OpenRouter — compatibility shim
|--------------------------------------------------------------------------
|
| The AI brain has moved to Gemini (config/ai.php -> `provider`). OpenRouter
| remains as the rollback driver, and the PAL intelligence subsystem still calls
| it directly through these keys — so this file stays, but it no longer owns the
| credentials. Every value derives from config/ai.php.
|
| New code reads config('ai.provider.openrouter.*'), or depends on
| App\Domain\AI\Support\ModelClient and lets AI_PROVIDER decide.
|
*/

$provider = config('ai.provider.openrouter', []);
$apiKey = $provider['api_key'] ?? null;

return [
    'api_key' => $apiKey,
    'base_url' => $provider['base_url'] ?? 'https://openrouter.ai/api/v1',
    'model' => $provider['model'] ?? 'deepseek/deepseek-chat',

    'headers' => [
        'Authorization' => 'Bearer ' . (string) $apiKey,
        'Content-Type' => 'application/json',
        'HTTP-Referer' => env('APP_URL', 'http://localhost'),
        'X-Title' => env('APP_NAME', 'LMS'),
    ],
];
