<?php

namespace App\Http\Controllers\api\OrganizationManagement\RolePermissions;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\user\tbluserModel;
use App\Models\user\tbluserprofilemasterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Ported from G2G's (hp_erp) `App\Http\Controllers\user\tblmenumasterG2gController`
 * (displayUserProfilesG2g / displayGroupwiseRightsG2g / storeGroupwiseRightsG2g /
 * storeUserProfileG2g), but re-pointed at LMS-K12's EXISTING role/rights tables
 * per product decision - NOT at new tblmenumaster_g2g/tblgroupwise_rights_g2g
 * tables:
 *   - roles              tbluserprofilemaster   (source: tbluserprofilemasterModel)
 *   - menu tree           tblmenumaster          (source: tblmenumaster_g2gModel)
 *   - role-level rights   tblgroupwise_rights    (source: tblgroupwise_rights_g2gModel)
 *
 * Field mapping, tblmenumaster (LMS-K12) -> tblmenumaster_g2g (G2G source):
 *   name -> menu_name, link -> access_link, menu_type -> page_type,
 *   parent_menu_id -> parent_id. tblgroupwise_rights already has can_view/
 *   can_add/can_edit/can_delete/dashboard_right/sub_institute_id
 *   (dashboard_right added by 2025_03_13_161022_add_column_dashboard_rights.php)
 *   plus `is_mobile`, added by this port's
 *   2026_08_19_175501_add_role_keys_and_is_mobile_rights_columns.php.
 *
 * Rights model: simple booleans only (can_view/can_add/can_edit/can_delete/
 * dashboard_right/is_mobile), matching exactly what
 * storeGroupwiseRightsG2g()/tblgroupwise_rights_g2gModel write in the
 * source - no tri-state allow/deny columns were added, since the source
 * screen does not use them.
 *
 * Auth: tenant/actor come from the session hydrated by `api.session`
 * (App\Http\Middleware\ApiSessionHydrator), not from G2G's
 * `ResolvesApiIdentity` bearer-token trait / request-supplied
 * sub_institute_id.
 *
 * Does NOT touch app/general/groupwise_rights or app/general/individual_rights -
 * those controllers/routes keep working unchanged; this controller only adds
 * new endpoints reading/writing the same tblgroupwise_rights table.
 */
class RolePermissionsController extends Controller
{
    private function tenant(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    /**
     * Roles and their rights (can_add/can_edit/can_delete/dashboard_right)
     * are institute-wide privilege grants, so only a Super Admin caller
     * (session `is_admin` 1 or 2 — see
     * App\Http\Middleware\Concerns\HydratesLegacyApiSession, the same
     * convention that stamps `user_profile_name = 'Super Admin'`) may create
     * a role or change what a role can do. This module has no separate
     * admin-equivalent role concept of its own to anchor the check to, so
     * `is_admin` is used directly.
     */
    private function assertIsAdmin(): ?\Illuminate\Http\JsonResponse
    {
        $isAdmin = (int) session()->get('is_admin');

        if ($isAdmin === 1 || $isAdmin === 2) {
            return null;
        }

        return response()->json([
            'status_code' => 0,
            'message' => 'You are not authorized to manage roles and permissions',
        ], 403);
    }

    /**
     * GET /organization-management/role-permissions/roles
     * Ported from displayUserProfilesG2g - every active role of the tenant,
     * with how many users currently sit on it.
     */
    public function index(Request $request)
    {
        $subInstituteId = $this->tenant();

        $profiles = tbluserprofilemasterModel::where([
            'sub_institute_id' => $subInstituteId,
            'status' => '1',
        ])->orderBy('sort_order')->get();

        $userCounts = tbluserModel::where('sub_institute_id', $subInstituteId)
            ->groupBy('user_profile_id')
            ->select('user_profile_id', DB::raw('COUNT(*) as total'))
            ->pluck('total', 'user_profile_id');

        $data = $profiles->map(fn ($profile) => [
            'id' => $profile->id,
            'name' => $profile->name,
            'description' => $profile->description,
            'sort_order' => $profile->sort_order,
            'role_key' => $profile->role_key ?? null,
            'data_scope' => $profile->data_scope ?? null,
            'is_system' => (bool) ($profile->is_system ?? false),
            'user_count' => (int) ($userCounts[$profile->id] ?? 0),
        ]);

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => $data,
        ]);
    }

    /**
     * POST /organization-management/role-permissions/roles
     * Ported from storeUserProfileG2g.
     */
    public function storeRole(Request $request)
    {
        if ($response = $this->assertIsAdmin()) {
            return $response;
        }

        $subInstituteId = $this->tenant();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'role_key' => 'nullable|string|max:64',
            'data_scope' => 'nullable|in:self,team,department,organization',
        ]);

        if ($validator->fails()) {
            return response()->json(['status_code' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $nextSortOrder = (int) tbluserprofilemasterModel::where('sub_institute_id', $subInstituteId)->max('sort_order') + 1;

        $profile = tbluserprofilemasterModel::create([
            'parent_id' => null,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'sort_order' => $nextSortOrder,
            'status' => '1',
            'sub_institute_id' => $subInstituteId,
            'client_id' => $subInstituteId,
            'role_key' => $request->input('role_key'),
            'data_scope' => $request->input('data_scope'),
        ]);

        AuditLog::record([
            'module' => 'permissions',
            'action' => 'role_created',
            'entity_type' => 'tbluserprofilemaster',
            'entity_id' => $profile->id,
            'new_values' => [
                'name' => $profile->name,
                'description' => $profile->description,
                'role_key' => $profile->role_key,
                'data_scope' => $profile->data_scope,
            ],
        ]);

        return response()->json([
            'status_code' => 1,
            'message' => 'Role created successfully',
            'data' => [
                'id' => $profile->id,
                'name' => $profile->name,
                'description' => $profile->description,
                'sort_order' => $profile->sort_order,
                'role_key' => $profile->role_key,
                'data_scope' => $profile->data_scope,
                'is_system' => (bool) ($profile->is_system ?? false),
                'user_count' => 0,
            ],
        ], 201);
    }

    /**
     * GET /organization-management/role-permissions/roles/{id}/rights
     * Ported from displayGroupwiseRightsG2g - full menu tree with the
     * target role's rights stamped on every node. Rows are never dropped
     * for lack of can_view, since the admin needs to see every box.
     */
    public function rights(Request $request, $id)
    {
        $subInstituteId = $this->tenant();

        $profile = tbluserprofilemasterModel::where('sub_institute_id', $subInstituteId)->find($id);
        if (!$profile) {
            return response()->json(['status_code' => 0, 'message' => 'Role not found'], 404);
        }

        $allMenus = DB::table('tblmenumaster')
            ->where('status', 1)
            ->whereRaw('FIND_IN_SET(?, sub_institute_id)', [$subInstituteId])
            ->orderBy('sort_order', 'ASC')
            ->get();

        $menusByParent = $allMenus->groupBy('parent_menu_id');

        $rightsByMenuId = DB::table('tblgroupwise_rights')
            ->where('profile_id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->get()
            ->keyBy('menu_id');

        $data = [];
        foreach ($menusByParent->get(0, []) as $module) {
            $moduleNode = $this->formatNode($module, $rightsByMenuId);
            $moduleNode['menus'] = $this->buildRightsTree($module->id, $menusByParent, $rightsByMenuId);
            $data[] = $moduleNode;
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => $data,
        ]);
    }

    /**
     * POST /organization-management/role-permissions/roles/{id}/rights
     * Ported from storeGroupwiseRightsG2g - replaces the whole rights set of
     * one role. Delete + insert runs in a transaction so a failed save
     * cannot leave the role with no rights at all. Rows with every flag
     * off are simply not stored - absence of a row means "no rights",
     * same as the source.
     */
    public function store(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) {
            return $response;
        }

        $subInstituteId = $this->tenant();

        $profile = tbluserprofilemasterModel::where('sub_institute_id', $subInstituteId)->find($id);
        if (!$profile) {
            return response()->json(['status_code' => 0, 'message' => 'Role not found'], 404);
        }

        $validator = Validator::make($request->all(), ['rights' => 'array']);
        if ($validator->fails()) {
            return response()->json(['status_code' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $rights = $request->input('rights', []);

        $validMenuIds = DB::table('tblmenumaster')
            ->where('status', 1)
            ->whereRaw('FIND_IN_SET(?, sub_institute_id)', [$subInstituteId])
            ->pluck('id')
            ->flip();

        $rows = [];
        foreach ($rights as $right) {
            $menuId = (int) ($right['menu_id'] ?? 0);
            if (!$menuId || !$validMenuIds->has($menuId)) {
                continue;
            }

            $row = [
                'menu_id' => $menuId,
                'profile_id' => (int) $id,
                'sub_institute_id' => $subInstituteId,
                'can_view' => $this->flag($right['can_view'] ?? 0),
                'can_add' => $this->flag($right['can_add'] ?? 0),
                'can_edit' => $this->flag($right['can_edit'] ?? 0),
                'can_delete' => $this->flag($right['can_delete'] ?? 0),
                'dashboard_right' => $this->flag($right['dashboard_right'] ?? 0),
                'is_mobile' => $this->flag($right['is_mobile'] ?? 0),
                'created_at' => now(),
            ];

            if ($row['can_view'] || $row['can_add'] || $row['can_edit'] || $row['can_delete'] || $row['dashboard_right'] || $row['is_mobile']) {
                $rows[] = $row;
            }
        }

        DB::transaction(function () use ($id, $subInstituteId, $rows) {
            DB::table('tblgroupwise_rights')->where('profile_id', $id)->where('sub_institute_id', $subInstituteId)->delete();

            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('tblgroupwise_rights')->insert($chunk);
            }
        });

        AuditLog::record([
            'module' => 'permissions',
            'action' => 'role_rights_update',
            'entity_type' => 'tblgroupwise_rights',
            'entity_id' => (int) $id,
            'new_values' => ['profile_id' => (int) $id, 'rights' => $rows],
        ]);

        return response()->json([
            'status_code' => 1,
            'message' => 'Groupwise rights saved successfully',
            'data' => ['saved' => count($rows)],
        ]);
    }

    private function buildRightsTree($parentId, $menusByParent, $rightsByMenuId): array
    {
        $nodes = [];

        foreach ($menusByParent->get($parentId, []) as $menu) {
            $menuNode = $this->formatNode($menu, $rightsByMenuId);
            $menuNode['submenus'] = $this->buildRightsTree($menu->id, $menusByParent, $rightsByMenuId);
            $nodes[] = $menuNode;
        }

        return $nodes;
    }

    private function flag($value): int
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) || (int) $value === 1 ? 1 : 0;
    }

    private function formatNode($node, $rightsByMenuId): array
    {
        $rights = $rightsByMenuId->get($node->id);

        return [
            'id' => $node->id,
            'label' => $node->name,
            'icon' => $node->icon,
            'access_link' => $node->link,
            'page_type' => $node->menu_type,
            'sort_order' => $node->sort_order,
            'can_view' => (int) ($rights->can_view ?? 0),
            'can_add' => (int) ($rights->can_add ?? 0),
            'can_edit' => (int) ($rights->can_edit ?? 0),
            'can_delete' => (int) ($rights->can_delete ?? 0),
            'dashboard_right' => (int) ($rights->dashboard_right ?? 0),
            'is_mobile' => (int) ($rights->is_mobile ?? 0),
        ];
    }
}
