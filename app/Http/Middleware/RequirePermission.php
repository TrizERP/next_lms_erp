<?php

namespace App\Http\Middleware;

use App\Services\Rbac\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Server-side permission gate: `->middleware('perm:lms.content,create')`.
 *
 * Delivers the enforcement half of tracker row 5 / Decision #37. The UI half (hiding or
 * disabling a button) is cosmetic; THIS is the check that decides.
 *
 * Decision #23 — "configuration can never grant a permission" — is what makes that
 * ordering non-negotiable. A client that hides a button proves nothing about what the
 * server will accept, so the server must refuse independently.
 *
 * Requires `lms.auth` to have run first, because it needs an identity to check. While
 * `lms_content.api_auth_enforce` is in warn-only mode there may be no identity at all; in
 * that case this middleware also warns rather than blocking, so the two flags flip
 * together and a half-rolled-out state cannot lock users out.
 */
class RequirePermission
{
    public function __construct(private PermissionService $permissions)
    {
    }

    public function handle(Request $request, Closure $next, string $module, string $action = 'view')
    {
        // ONE switch decides enforcement, for BOTH the "no identity" and the
        // "identity but no grant" cases.
        //
        // This originally warn-skipped only when `lms_auth` was null, so a caller
        // presenting a VALID token was permission-checked for real while the flag still
        // said warn-only. That was a live regression: the three legacy write routes
        // (lms-create-content, lms-store-content, lms-chapter-content/upload) started
        // returning 403 to any JWT client the moment the middleware shipped, and 59
        // (profile, tenant) pairs hold rights on menu 270 with no row on menu 236 at all
        // - every one of them a 403. Warn-only must mean warn-only for everybody.
        $enforcing = (bool) config('lms_content.api_auth_enforce', false);

        /** @var array<string,mixed>|null $auth */
        $auth = $request->attributes->get('lms_auth');

        if ($auth === null) {
            if (! $enforcing) {
                Log::channel('daily')->warning('perm: unverified identity, check skipped', [
                    'path' => $request->path(),
                    'module' => $module,
                    'action' => $action,
                ]);

                return $next($request);
            }

            return response()->json([
                'status_code' => 0,
                'message' => 'Authentication is required for this action.',
            ], 401);
        }

        // The tenant comes from the TOKEN, never from request input. Reading it from the
        // body is what makes tenancy caller-controlled on the legacy endpoints.
        $allowed = $this->permissions->check(
            (int) $auth['user_id'],
            $auth['user_profile_id'] ?? null,
            $auth['sub_institute_id'],
            $module,
            $action
        );

        if ($allowed) {
            return $next($request);
        }

        if (! $enforcing) {
            // The whole point of the rollout: log exactly who WOULD be denied, and let
            // them through. This log is the go/no-go evidence for flipping the switch -
            // if it is noisy, the rights data is wrong, not the caller.
            Log::channel('daily')->warning('perm: would have DENIED (warn-only)', [
                'path' => $request->path(),
                'module' => $module,
                'action' => $action,
                'user_id' => $auth['user_id'] ?? null,
                'profile_id' => $auth['user_profile_id'] ?? null,
                'sub_institute_id' => $auth['sub_institute_id'] ?? null,
            ]);

            return $next($request);
        }

        return response()->json([
            'status_code' => 0,
            'message' => sprintf('You do not have permission to %s this resource.', $action),
            'module' => $module,
            'action' => $action,
        ], 403);
    }
}
