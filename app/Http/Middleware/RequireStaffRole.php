<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Minimum-viable authorization gate for the HR/task-management modules
 * (Talent Management, Task Management, Competency Management) that have no
 * pre-existing menu_id/rights table to check against like the legacy
 * modules do.
 *
 * Runs after `api.session` (App\Http\Middleware\ApiSessionHydrator), which
 * hydrates `session()->get('user_profile_name')` from the verified JWT
 * payload, so the value read here cannot be spoofed by the caller. Super
 * Admin callers are stamped `user_profile_name = 'Super Admin'` by
 * App\Http\Middleware\Concerns\HydratesLegacyApiSession and always pass.
 *
 * This does NOT implement field-level RBAC for these modules - it only
 * closes the "any authenticated party, including students/parents, can
 * read/write HR and task data" gap by rejecting the bare Student/Parent
 * profiles. Any other authenticated staff-side profile (Admin, Teacher,
 * HR, etc.) is allowed through unchanged.
 */
class RequireStaffRole
{
    /**
     * Profile names that must never reach HR/task-management endpoints.
     */
    private const BLOCKED_PROFILES = [
        'student',
        'parent',
    ];

    public function handle(Request $request, Closure $next)
    {
        $profileName = strtolower((string) session()->get('user_profile_name'));
        $isStudent = (bool) session()->get('is_student');

        if ($isStudent || in_array($profileName, self::BLOCKED_PROFILES, true)) {
            return response()->json([
                'status_code' => 0,
                'message' => 'You are not authorized to access this resource',
            ], 403);
        }

        return $next($request);
    }
}
