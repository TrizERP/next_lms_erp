<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\Rbac\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-action permission flags for the frontend.
 *
 * GET /api/permissions?modules=lms.content,lms.question_bank
 *   -> {"data": {"lms.content": {"view":true,"create":false,"update":false,"delete":false}}}
 *
 * WHY A NEW ENDPOINT RATHER THAN EXTENDING /api/menu-rights
 *
 * `/api/menu-rights` cannot answer this question. MenuRightsController::getMenuRightsLevelWise
 * selects `GROUP_CONCAT(distinct m.id) AS MID` — it returns WHICH MENUS EXIST and nothing
 * else. The frontend's only RBAC today is therefore "did this menu item come back?", which
 * is why no content screen has ever been able to gate a button: it has never had per-action
 * data to gate on.
 *
 * That endpoint is also consumed by the sidebar in EVERY module, so widening its response
 * shape would be a cross-team break for no benefit. A separate, additive endpoint costs
 * nothing and touches nobody.
 *
 * THIS IS ADVISORY, NOT ENFORCEMENT. It exists so the UI can disable a button the user
 * cannot use. The authoritative check is the `perm:` middleware on the write route. A
 * client that ignores this endpoint gains nothing — Decision #23.
 */
class PermissionsController extends Controller
{
    public function __construct(private PermissionService $permissions)
    {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var array<string,mixed>|null $auth */
        $auth = $request->attributes->get('lms_auth');

        $requested = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $request->query('modules', ''))
        )));

        $known = $this->permissions->modules();
        $modules = $requested === [] ? $known : array_values(array_intersect($requested, $known));

        $unknown = array_values(array_diff($requested, $known));

        if ($auth === null) {
            // Warn-only rollout: identity is unverified, so no honest answer is possible.
            // Report that explicitly rather than returning all-false (which the UI would
            // render as "you have no rights") or all-true (which would be a lie).
            return response()->json([
                'status_code' => 0,
                'message' => 'Unauthenticated — permission flags are unavailable.',
                'authenticated' => false,
                'modules' => $modules,
                'unknown_modules' => $unknown,
                'data' => (object) [],
            ], 200);
        }

        $data = [];
        foreach ($modules as $module) {
            $data[$module] = $this->permissions->actionsFor(
                (int) $auth['user_id'],
                $auth['user_profile_id'] ?? null,
                $auth['sub_institute_id'],
                $module
            );
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'authenticated' => true,
            'user_id' => (int) $auth['user_id'],
            'sub_institute_id' => $auth['sub_institute_id'],
            'unknown_modules' => $unknown,
            'data' => $data,
        ], 200);
    }
}
