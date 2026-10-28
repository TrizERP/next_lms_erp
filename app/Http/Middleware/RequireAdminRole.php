<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Admin-only routes: school or platform settings that hold credentials or
 * redirect school-wide traffic. Run after `api.session` or `session`.
 *
 * Staff in general (RequireStaffRole) is too wide for these: a teacher or
 * librarian should not change the school's SMS, mail or AI provider keys.
 */
class RequireAdminRole
{
    public function handle(Request $request, Closure $next)
    {
        // Routes behind McpAuth carry the verified identity in a request attribute,
        // not in the session; everything else is hydrated into the session.
        $mcp = $request->attributes->get('mcp_auth');
        if (is_array($mcp)) {
            $allowed = ($mcp['role'] ?? '') === 'admin' && empty($mcp['is_student']);
        } else {
            $profile = strtolower((string) session()->get('user_profile_name'));
            $isStudent = (bool) session()->get('is_student') || in_array($profile, ['student', 'parent'], true);
            $allowed = !$isStudent && str_contains($profile, 'admin');
        }

        if (!$allowed) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You are not authorized to access this resource',
            ], 403);
        }

        return $next($request);
    }
}
