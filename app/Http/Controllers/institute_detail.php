<?php

namespace App\Http\Controllers;

use App\Http\Controllers\settings\instituteDetailController;

/**
 * New, additive-only class. routes/web.php has a long-standing
 * `use App\Http\Controllers\institute_detail;` import and an
 * `institute_detail::class` route binding (add-institute-details) for a
 * class that never existed under this namespace — only
 * App\Http\Controllers\settings\instituteDetailController does. That made
 * `institute_detail::class` unresolvable, which crashed any command that
 * reflects over registered controllers (e.g. `php artisan route:list`) with
 * "Class App\Http\Controllers\institute_detail does not exist".
 *
 * Per the "existing code must remain untouched, add new code only" migration
 * rule, routes/web.php's existing `use`/route line is left exactly as-is.
 * This new file simply makes the class it already references actually exist,
 * by extending the real, unmodified settings\instituteDetailController — so
 * the existing `add-institute-details` route inherits its real behavior
 * instead of fatally erroring, without changing a single existing file.
 */
class institute_detail extends instituteDetailController
{
}
