<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\Models\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],
    ],

    'attendance' => [
        'url' => env('ATTENDANCE_API_URL', 'https://erp.triz.co.in/student/studentAttendanceChatAPI'),
        'token' => env('ATTENDANCE_API_TOKEN'),
        'sub_institute_id' => env('ATTENDANCE_SUB_INSTITUTE_ID'),
        'syear' => env('ATTENDANCE_SYEAR'),
    ],

    'erp' => [
        'url' => env('ERP_API_URL', 'https://erp.triz.co.in'),
        'token' => env('ERP_API_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | G2G (hp_erp) cross-product Competency/KASBA/Framework graph
    |--------------------------------------------------------------------------
    |
    | No default base_url: unlike `erp` above, there is no known-good deployed
    | host for hp_erp today (its own .env points APP_URL at 127.0.0.1:8000, a
    | local dev server, not a stable address this application can default to).
    | Both values are empty until someone sets them, and
    | App\Services\Integrations\G2GCompetencyGraphClient treats that as a real
    | "not configured" state rather than guessing a host.
    |
    | G2G_API_TOKEN must be a Sanctum personal access token from hp_erp's own
    | `personal_access_tokens` table, ideally a dedicated service account's -
    | see that client class's docblock for why none is defaulted here and what
    | was checked before concluding one does not exist yet.
    |
    */
    'g2g' => [
        'base_url' => env('G2G_API_BASE_URL'),
        'token' => env('G2G_API_TOKEN'),
        'timeout' => env('G2G_API_TIMEOUT', 8),
    ],

    /*
    | Meta WhatsApp Cloud API callbacks (delivery receipts). Both are required and neither has a default, so an
    | unconfigured server accepts nothing: see WhatsappWebhookController.
    |   WHATSAPP_WEBHOOK_VERIFY_TOKEN  any secret string; the same one is typed into Meta's webhook settings.
    |   WHATSAPP_APP_SECRET            the Meta app's "App secret"; used to check each callback's signature.
    */
    'whatsapp' => [
        'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
    ],
];
