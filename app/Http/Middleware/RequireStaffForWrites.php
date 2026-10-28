<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Lets any signed-in caller read, but only staff change data. For module APIs whose
 * reads are shared with students and parents while every write (create, edit, delete,
 * approve) is a staff action. Run after `api.session`.
 */
class RequireStaffForWrites
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        return app(RequireStaffRole::class)->handle($request, $next);
    }
}
