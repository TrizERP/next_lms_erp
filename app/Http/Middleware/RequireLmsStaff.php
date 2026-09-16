<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Staff-only gate for routes authenticated by `lms.auth`.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS EXISTS RATHER THAN REUSING RequireStaffRole
 * ---------------------------------------------------------------------------
 * `RequireStaffRole` answers the same question, but it reads
 * `session()->get('user_profile_name')`, which `api.session`
 * (App\Http\Middleware\ApiSessionHydrator) puts there. The platform-services
 * routes authenticate with `lms.auth` instead, and that middleware sets a
 * request ATTRIBUTE (`lms_auth`) without hydrating a legacy session. Dropped onto
 * these routes, RequireStaffRole would read an empty profile name, find
 * `is_student` unset, and allow everybody — a gate that silently passes is worse
 * than no gate, because it looks like one in the route file.
 *
 * Extending RequireStaffRole to fall back to `lms_auth` was the alternative. It
 * was rejected because that middleware guards the Talent, Task and Competency
 * modules: making it stricter changes behaviour on routes this work has no
 * business touching. A separate class keeps the blast radius at the routes that
 * opt in.
 *
 * ---------------------------------------------------------------------------
 * WHY THE PROFILE NAME IS LOOKED UP AND `is_student` IS NOT ENOUGH
 * ---------------------------------------------------------------------------
 * `is_student` is set by ApiLoginController only when the credentials resolved
 * against `tblstudent` (ApiLoginController:88). A PARENT with a `tbluser` row
 * takes the staff branch, so their token reads `is_student = false` and they
 * would pass a flag-only check. The profile name is the only thing that
 * separates them, and it is not in the JWT — so it is read from
 * `tbluserprofilemaster` by the token's `user_profile_id`, which the caller
 * cannot influence.
 *
 * One indexed primary-key lookup per request, and only for identified callers.
 *
 * Names are normalised the way DocumentAggregationController already normalises
 * them, because live profile rows are not clean: the same role appears as
 * "ADMIN", "Admin", "PRINCIPAL " with a trailing space and "collage_admin".
 * Comparing raw strings would block one tenant's parent and admit another's.
 *
 * ---------------------------------------------------------------------------
 * A BLOCKLIST, NOT AN ALLOWLIST — AND DELIBERATELY SO
 * ---------------------------------------------------------------------------
 * Same shape as RequireStaffRole: reject the two profiles that must never see
 * operational data, and let every other staff profile through. An allowlist
 * would be tighter, but profile names are tenant-authored free text, so a
 * whitelist locks out any school whose administrator is called something the
 * list did not anticipate. Narrowing further is RBAC's job — see
 * `perm:platform.eventbus,view`, which is the gate that decides WHICH staff.
 *
 * ---------------------------------------------------------------------------
 * AN UNIDENTIFIED CALLER IS PASSED THROUGH, NOT BLOCKED
 * ---------------------------------------------------------------------------
 * `lms.auth` is in warn-only mode (`lms_content.api_auth_enforce`), so a request
 * with no token arrives with `lms_auth = null`. This middleware cannot tell a
 * student from an administrator at that point, so it does not guess: it defers,
 * and PlatformController answers 401 because there is no tenant to scope to.
 * Refusing here with 403 instead would report the wrong reason for the wrong
 * problem.
 */
class RequireLmsStaff
{
    /** Normalised profile names that must never reach an operations screen. */
    private const BLOCKED_PROFILES = ['student', 'parent'];

    public function handle(Request $request, Closure $next)
    {
        /** @var array<string,mixed>|null $auth */
        $auth = $request->attributes->get('lms_auth');

        // No verified identity: let it pass and let the controller answer 401.
        // See the class docblock — this is a deferral, not a grant.
        if ($auth === null) {
            return $next($request);
        }

        if (! empty($auth['is_student'])) {
            return $this->forbidden();
        }

        if ($this->isBlockedProfile($auth['user_profile_id'] ?? null)) {
            return $this->forbidden();
        }

        return $next($request);
    }

    private function isBlockedProfile(mixed $profileId): bool
    {
        $id = is_numeric($profileId) ? (int) $profileId : 0;

        if ($id <= 0) {
            // A token with no profile cannot be shown to be staff. It is also not
            // shown to be a student, and blocking it would lock out any account
            // whose profile row was deleted. Left to RBAC, which fails closed.
            return false;
        }

        $name = DB::table('tbluserprofilemaster')->where('id', $id)->value('name');

        return in_array($this->normalise((string) $name), self::BLOCKED_PROFILES, true);
    }

    /** Lowercase, underscores to spaces, whitespace collapsed — as Documents does. */
    private function normalise(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower(str_replace('_', ' ', $value))) ?? '');
    }

    private function forbidden()
    {
        return response()->json([
            'status_code' => 0,
            'message' => 'This screen is available to staff accounts only.',
            'data' => null,
        ], 403);
    }
}
