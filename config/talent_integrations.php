<?php

/*
|--------------------------------------------------------------------------
| Talent Management integrations
|--------------------------------------------------------------------------
|
| OAuth 2 providers the Administration -> Integrations tab can connect to. "Connect" sends the user to
| the provider's own consent page; the provider sends them back to
| `{APP_URL}/api/talent/admin/integrations/callback`, which must be registered as a redirect URI in
| the provider's developer console.
|
| A provider only becomes connectable once its client id and secret are set in .env. Until then the
| Connect button explains exactly which keys are missing instead of pretending to connect.
|
*/
return [
    'oauth' => [
        'google' => [
            'label' => 'Google',
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'env_keys' => ['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET'],
            'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'userinfo_url' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'scopes' => ['openid', 'email', 'https://www.googleapis.com/auth/calendar.events'],
            'extra_authorize' => ['access_type' => 'offline', 'prompt' => 'consent'],
            'account_field' => 'email',
            'console_url' => 'https://console.cloud.google.com/apis/credentials',
        ],
        'microsoft' => [
            'label' => 'Microsoft',
            'client_id' => env('MICROSOFT_CLIENT_ID'),
            'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
            'env_keys' => ['MICROSOFT_CLIENT_ID', 'MICROSOFT_CLIENT_SECRET'],
            'authorize_url' => 'https://login.microsoftonline.com/' . env('MICROSOFT_TENANT', 'common') . '/oauth2/v2.0/authorize',
            'token_url' => 'https://login.microsoftonline.com/' . env('MICROSOFT_TENANT', 'common') . '/oauth2/v2.0/token',
            'userinfo_url' => 'https://graph.microsoft.com/v1.0/me',
            'scopes' => ['offline_access', 'User.Read', 'Calendars.ReadWrite'],
            'extra_authorize' => ['response_mode' => 'query'],
            'account_field' => 'mail',
            'console_url' => 'https://entra.microsoft.com/',
        ],
        'linkedin' => [
            'label' => 'LinkedIn',
            'client_id' => env('LINKEDIN_CLIENT_ID'),
            'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
            'env_keys' => ['LINKEDIN_CLIENT_ID', 'LINKEDIN_CLIENT_SECRET'],
            'authorize_url' => 'https://www.linkedin.com/oauth/v2/authorization',
            'token_url' => 'https://www.linkedin.com/oauth/v2/accessToken',
            'userinfo_url' => 'https://api.linkedin.com/v2/userinfo',
            'scopes' => ['openid', 'profile', 'email'],
            'extra_authorize' => [],
            'account_field' => 'email',
            'console_url' => 'https://www.linkedin.com/developers/apps',
        ],
        'docusign' => [
            'label' => 'DocuSign',
            'client_id' => env('DOCUSIGN_CLIENT_ID'),
            'client_secret' => env('DOCUSIGN_CLIENT_SECRET'),
            'env_keys' => ['DOCUSIGN_CLIENT_ID', 'DOCUSIGN_CLIENT_SECRET'],
            // Set DOCUSIGN_ENV=production for live accounts; the developer sandbox is the default.
            'authorize_url' => (env('DOCUSIGN_ENV', 'demo') === 'production' ? 'https://account.docusign.com' : 'https://account-d.docusign.com') . '/oauth/auth',
            'token_url' => (env('DOCUSIGN_ENV', 'demo') === 'production' ? 'https://account.docusign.com' : 'https://account-d.docusign.com') . '/oauth/token',
            'userinfo_url' => (env('DOCUSIGN_ENV', 'demo') === 'production' ? 'https://account.docusign.com' : 'https://account-d.docusign.com') . '/oauth/userinfo',
            'scopes' => ['signature', 'extended'],
            'extra_authorize' => [],
            'basic_auth_token' => true,
            'account_field' => 'email',
            'console_url' => 'https://admindemo.docusign.com/apps-and-keys',
        ],
    ],
];
