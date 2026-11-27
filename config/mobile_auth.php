<?php

/**
 * Mobile number + mPIN sign-in (api/mobile-auth/*): how the one-time code is delivered.
 *
 * The code goes to the person's WhatsApp first (the school's WhatsApp Cloud API credentials from
 * `whatapp_user_details`), and falls back to the school's SMS gateway when WhatsApp is not set up for
 * the school or the message is refused. A person can also ask for SMS directly in the app.
 */
return [
    // A dev machine points at real school data, so by default NOTHING is sent when APP_ENV=local: the code is
    // written to storage/logs/laravel.log. Set MOBILE_AUTH_SEND_IN_LOCAL=true to really send (use your own number).
    'send_in_local' => (bool) env('MOBILE_AUTH_SEND_IN_LOCAL', false),

    // WhatsApp only delivers a business-started message through a template Meta has approved.
    //
    // Default: the generic body template the rest of the ERP already sends through (`parents_`, one text
    // variable), carrying the whole message. Better: create a WhatsApp *Authentication* template ("OTP") in
    // Meta Business Manager and put its name here; the code is then sent as its body variable and as the
    // "Copy code" button, which is what WhatsApp expects for login codes.
    'whatsapp_template' => env('MOBILE_AUTH_WA_TEMPLATE', ''),
    'whatsapp_template_has_copy_button' => (bool) env('MOBILE_AUTH_WA_COPY_BUTTON', true),
    'whatsapp_fallback_template' => env('MOBILE_AUTH_WA_FALLBACK_TEMPLATE', 'parents_'),
    'whatsapp_languages' => ['en_US', 'en', 'en_GB'],
    'whatsapp_graph_version' => env('MOBILE_AUTH_WA_GRAPH_VERSION', 'v25.0'),
];
