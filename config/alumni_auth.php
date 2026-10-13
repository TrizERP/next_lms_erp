<?php

/*
|--------------------------------------------------------------------------
| Alumni portal authentication
|--------------------------------------------------------------------------
| Alumni sign in with their own email and password and receive tokens that are NOT interchangeable with staff, student or
| parent tokens: a different signing secret, a different audience and different claim names. See section 8 of
| docs/ALUMNI_MODULE_IMPLEMENTATION_PLAN.md.
|
| ALUMNI_JWT_SECRET must be a random string of at least 32 characters and must differ from JWT_SECRET. When it is missing
| or weak the portal answers 503 and refuses to issue or accept any token - it never falls back to the staff secret.
*/

return [
    'jwt_secret' => env('ALUMNI_JWT_SECRET'),

    // The STAFF signing secret, read here only so the service can refuse to start when the two are the same. It must live in
    // this file: env() returns null outside config files once `php artisan config:cache` has run, which would silently turn
    // the check off. It is never used to sign or verify anything by the portal.
    'staff_jwt_secret' => env('JWT_SECRET'),

    // Public address of the alumni portal, used in the links emailed to alumni (no trailing slash). Falls back to the
    // 'alumni_portal_url' institute setting, then to APP_URL.
    'portal_url' => env('ALUMNI_PORTAL_URL'),

    'access_ttl_minutes' => 30,
    'refresh_ttl_days' => 30,           // absolute life of a login; the refresh token itself is rotated on every use
    'invite_ttl_days' => 7,
    'verify_email_ttl_hours' => 24,
    'reset_ttl_minutes' => 60,

    'max_failed_logins' => 5,
    'lockout_minutes' => 15,

    'password_min_length' => 10,
];
