<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Gemini — compatibility shim
|--------------------------------------------------------------------------
|
| The provider settings now live in ONE place: config/ai.php, under `provider`.
| This file stays only so existing `config('gemini.*')` readers keep working, and
| it derives every value from that block rather than re-reading the environment —
| which is how the three provider configs previously drifted into disagreeing
| about the default model.
|
| Add nothing here. New code reads config('ai.provider.gemini.*'), or better,
| depends on App\Domain\AI\Support\ModelClient and never names a provider at all.
|
*/

$provider = config('ai.provider.gemini', []);

return [
    'api_key' => $provider['api_key'] ?? null,
    'base_url' => $provider['base_url'] ?? null,
    'request_timeout' => $provider['timeout'] ?? 45,
    'model' => $provider['model'] ?? 'gemini-2.5-flash',
];
