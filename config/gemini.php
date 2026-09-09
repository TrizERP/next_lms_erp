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
    | Gemini Model
    |--------------------------------------------------------------------------
    |
    | Model id used for :generateContent calls. Google retires ids and then
    | answers them with 404 NOT_FOUND, which surfaces to the user as a failed
    | generation. Retired ids are mapped forward here so a server whose .env
    | still pins an old one keeps working after this deploy - drop GEMINI_MODEL
    | from .env entirely to just track the default.
    */

    'model' => (static function () {
        $model = trim((string) env('GEMINI_MODEL', ''));

        $retired = [
            'gemini-2.5-flash' => 'gemini-3.6-flash',
            'gemini-1.5-flash' => 'gemini-3.6-flash',
            'gemini-1.5-pro'   => 'gemini-3.6-pro',
        ];

        return $retired[$model] ?? ($model !== '' ? $model : 'gemini-3.6-flash');
    })(),

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
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout may be used to specify the maximum number of seconds to wait
    | for a response. By default, the client will time out after 30 seconds.
    */

    'request_timeout' => env('GEMINI_REQUEST_TIMEOUT', 30),
];
