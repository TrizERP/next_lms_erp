<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fail closed on write routes that have no menu row
    |--------------------------------------------------------------------------
    |
    | checkPermission finds a route's rights through `tblmenumaster.link = <route name>`. A write route
    | with no such row passes the check for everyone. While this is false that stays the case, but the
    | gap is logged once an hour per route ("perm: write route has no menu row").
    |
    | Turn it on only after mapping the routes listed by `php artisan routes:unmapped-writes`, or after
    | naming the deliberate exceptions in PERM_UNMAPPED_ALLOW (comma separated route names, for example
    | public or student-facing writes that have no menu entry).
    |
    */
    'fail_closed' => (bool) env('PERM_FAIL_CLOSED', false),

    'unmapped_allow' => array_values(array_filter(array_map('trim', explode(',', (string) env('PERM_UNMAPPED_ALLOW', ''))))),
];
