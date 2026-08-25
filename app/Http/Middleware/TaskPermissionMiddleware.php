<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Backs `task.permission:{ability}`. Ported from hp_erp's
 * TaskPermissionMiddleware (G2G), adapted to this codebase's identity
 * mechanism: it runs after `api.session` (App\Http\Middleware\ApiSessionHydrator),
 * which has already hydrated `session()->get('user_profile_id')` from the
 * verified JWT payload - see App\Http\Middleware\RequireStaffRole for the
 * same, established pattern - so this middleware trusts the session rather
 * than re-resolving a token itself.
 *
 * A PRIVILEGED ability requires proof of an ELEVATED role; anything else any
 * authenticated staff user may do. The role comes from
 * tbluser.user_profile_id -> tbluserprofilemaster.role_key. Unresolved is
 * refused - a missing role is not evidence of seniority.
 */
class TaskPermissionMiddleware
{
    /** Abilities that alter or remove other people's work, or expose org-wide data. */
    private const PRIVILEGED = [
        'task.delete',
        'task.approve',
        'project.create',
        'project.manage',
        'workstream.manage',
        'dependency.manage',
        'milestone.manage',
        'notification.manage',
        // Reading every employee's productivity, or the permission matrix, is
        // an administrative act even though it is a GET.
        'report.view',
    ];

    /**
     * Roles that may perform the abilities above. Includes the two
     * people-management roles: deleting or approving a subordinate's task is
     * a manager's job, not only an admin's.
     */
    private const ELEVATED = [
        'administrator',
        'hr_manager',
        'hr_executive',
        'executive',
        'reporting_manager',
        'department_head',
    ];

    /** Profiles that predate role_key, resolved by EXACT name. */
    private const LEGACY_NAMES = [
        'admin'                      => 'administrator',
        'organization administrator' => 'administrator',
        'hr'                         => 'hr_manager',
    ];

    public function handle(Request $request, Closure $next, string $ability = '')
    {
        // Matches the established Super Admin convention already used by
        // RolePermissionsController::assertIsAdmin() / RequiresTalentAdmin
        // elsewhere in this codebase: is_admin 1 or 2, sourced from the
        // JWT-verified session.
        $isAdmin = (int) session()->get('is_admin');

        if ($isAdmin === 1 || $isAdmin === 2) {
            return $next($request);
        }

        if (in_array($ability, self::PRIVILEGED, true) && ! $this->isPrivileged()) {
            return response()->json([
                'status' => 0,
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }

    private function isPrivileged(): bool
    {
        $profileId = (int) (session()->get('user_profile_id') ?? 0);

        if ($profileId <= 0) {
            return false;
        }

        $profile = DB::table('tbluserprofilemaster')
            ->where('id', $profileId)
            ->first(['role_key', 'name']);

        if (!$profile) {
            return false;
        }

        $roleKey = trim((string) ($profile->role_key ?? ''));

        if ($roleKey === '') {
            // EXACT name match, never a substring.
            $roleKey = self::LEGACY_NAMES[strtolower(trim((string) $profile->name))] ?? '';
        }

        return $roleKey !== '' && in_array($roleKey, self::ELEVATED, true);
    }
}
