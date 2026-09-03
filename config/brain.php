<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enterprise Brain role bridge
    |--------------------------------------------------------------------------
    |
    | The LMS remains the source of users and profiles. These settings map the
    | profile/admin values already present in the LMS JWT to Brain roles.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Accepted JWT signing secrets
    |--------------------------------------------------------------------------
    |
    | The Brain verifies the LMS's own token; it issues none of its own. When the
    | LMS front end signs in against a DIFFERENT deployment from the one serving
    | /api/brain, the two must agree on the signing secret or every Brain request
    | comes back brain_invalid_token. List that deployment's JWT_SECRET here
    | (comma-separated in BRAIN_JWT_SECRETS) rather than weakening the check.
    |
    */
    'jwt_secrets' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('BRAIN_JWT_SECRETS', ''))
    ))),

    'default_role' => 'viewer',

    'admin_values' => [1, 2],

    'profile_roles' => [
        // user_profile_id => brain role
    ],
];
