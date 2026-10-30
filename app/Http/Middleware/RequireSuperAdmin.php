<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Restricts a route to Super Admin callers. Run it after `session`, which
 * has already rejected anonymous requests and hydrated the identity.
 */
class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $isSuperAdmin = strtolower((string) session()->get('user_profile_name')) === 'super admin';

        if (!$isSuperAdmin) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You are not authorized to access this resource',
            ], 403);
        }

        return $next($request);
    }
}
