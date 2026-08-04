<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GroupwiseRightsApiController extends Controller
{
    use GetsJwtToken;

    private const ROUTE_NAME = 'add_groupwise_rights.index';

    private function failure(string $message, int $status = 422, $errors = null)
    {
        return response()->json([
            'status_code' => 0,
            'message' => $message,
            'errors' => $errors,
            'data' => [],
        ], $status);
    }

    private function hasDashboardRightColumn(): bool
    {
        return Schema::hasColumn('tblgroupwise_rights', 'dashboard_right');
    }

    private function hasMenuTypeColumn(): bool
    {
        return Schema::hasColumn('tblmenumaster', 'menu_type');
    }

    private function context(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                return response()->json(['status_code' => 2, 'message' => 'Token Auth Failed', 'data' => []], 401);
            }
        } catch (\Exception $exception) {
            return response()->json(['status_code' => 2, 'message' => $exception->getMessage(), 'data' => []], 401);
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'user_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $token = preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization'));
        $parts = explode('.', $token);
        $payload = [];
        if (count($parts) === 3) {
            $decoded = base64_decode(strtr($parts[1], '-_', '+/'));
            $payload = json_decode($decoded ?: '{}', true) ?: [];
        }

        $actorId = (int) ($payload['id'] ?? 0);
        $tenantId = (int) ($payload['sub_institute_id'] ?? 0);
        if ($actorId !== $request->integer('user_id') || $tenantId !== $request->integer('sub_institute_id')) {
            return response()->json(['status_code' => 2, 'message' => 'Token context does not match the request.', 'data' => []], 403);
        }

        $actor = DB::table('tbluser as user')
            ->join('tbluserprofilemaster as profile', 'profile.id', '=', 'user.user_profile_id')
            ->select('user.id', 'user.user_profile_id', 'user.sub_institute_id', 'profile.name as profile_name')
            ->where('user.id', $actorId)
            ->where('user.sub_institute_id', $tenantId)
            ->where('user.status', 1)
            ->first();

        if (! $actor) {
            return response()->json(['status_code' => 2, 'message' => 'Active user context was not found.', 'data' => []], 403);
        }

        $request->attributes->set('groupwise_actor', $actor);

        return null;
    }

    private function permissions(Request $request): array
    {
        $actor = $request->attributes->get('groupwise_actor');
        if (in_array(strtolower((string) $actor->profile_name), ['admin', 'super admin'], true)) {
            return ['view' => true, 'add' => true, 'edit' => true, 'delete' => true, 'admin' => true];
        }

        $menuId = DB::table('tblmenumaster')->where('status', 1)->where('link', self::ROUTE_NAME)->value('id');
        $rights = null;
        if ($menuId) {
            $rights = DB::table('tblindividual_rights')
                ->where('menu_id', $menuId)
                ->where('profile_id', $actor->user_profile_id)
                ->where('user_id', $actor->id)
                ->where('sub_institute_id', $actor->sub_institute_id)
                ->first();

            if (! $rights) {
                $rights = DB::table('tblgroupwise_rights')
                    ->where('menu_id', $menuId)
                    ->where('profile_id', $actor->user_profile_id)
                    ->where('sub_institute_id', $actor->sub_institute_id)
                    ->first();
            }
        }

        return [
            'view' => (bool) ($rights->can_view ?? false),
            'add' => (bool) ($rights->can_add ?? false),
            'edit' => (bool) ($rights->can_edit ?? false),
            'delete' => (bool) ($rights->can_delete ?? false),
            'admin' => false,
        ];
    }

    private function authorizeAction(Request $request, string $action)
    {
        $permissions = $this->permissions($request);
        if (! ($permissions[$action] ?? false)) {
            return $this->failure('You do not have permission to ' . $action . ' this resource.', 403);
        }

        return null;
    }

    private function activeProfile(Request $request, int $profileId)
    {
        $actor = $request->attributes->get('groupwise_actor');

        return DB::table('tbluserprofilemaster')
            ->select('id', 'name', 'status', 'sort_order')
            ->where('sub_institute_id', $actor->sub_institute_id)
            ->where('id', $profileId)
            ->where('status', 1)
            ->first();
    }

    private function menuTreeQuery(int $tenantId, int $profileId, int $level)
    {
        $query = DB::table('tblmenumaster')
            ->join('tblprofilewise_menu', 'tblmenumaster.id', '=', 'tblprofilewise_menu.menu_id')
            ->where('tblmenumaster.level', $level)
            ->where('tblmenumaster.status', 1)
            ->where('tblprofilewise_menu.user_profile_id', $profileId)
            ->where('tblprofilewise_menu.sub_institute_id', $tenantId)
            ->orderBy('tblmenumaster.sort_order', 'ASC')
            ->select(
                'tblmenumaster.id',
                DB::raw('tblmenumaster.id as menu_id'),
                'tblmenumaster.parent_menu_id',
                'tblmenumaster.name',
                'tblmenumaster.sort_order'
            );

        $query->addSelect($this->hasMenuTypeColumn()
            ? 'tblmenumaster.menu_type'
            : DB::raw("'' as menu_type"));

        return $query;
    }

    private function matrixRows(int $tenantId, int $profileId): array
    {
        $mainMenus = $this->menuTreeQuery($tenantId, $profileId, 1)
            ->groupBy('tblmenumaster.id', 'tblmenumaster.parent_menu_id', 'tblmenumaster.name', 'tblmenumaster.sort_order')
            ->when($this->hasMenuTypeColumn(), function ($query) {
                $query->groupBy('tblmenumaster.menu_type');
            })
            ->get();

        $subMenus = $this->menuTreeQuery($tenantId, $profileId, 2)
            ->get()
            ->groupBy('parent_menu_id');

        $thirdMenus = $this->menuTreeQuery($tenantId, $profileId, 3)
            ->get()
            ->groupBy('parent_menu_id');

        $rows = [];
        foreach ($mainMenus as $mainMenu) {
            $mainId = (int) $mainMenu->id;
            $rows[] = [
                'menu_id' => $mainId,
                'name' => (string) $mainMenu->name,
                'level' => 1,
                'menu_type' => (string) ($mainMenu->menu_type ?? ''),
            ];

            foreach ($subMenus->get($mainId, collect()) as $subMenu) {
                $subId = (int) $subMenu->id;
                $rows[] = [
                    'menu_id' => $subId,
                    'name' => (string) $subMenu->name,
                    'level' => 2,
                    'menu_type' => (string) ($subMenu->menu_type ?? ''),
                ];

                foreach ($thirdMenus->get($subId, collect()) as $thirdMenu) {
                    $rows[] = [
                        'menu_id' => (int) $thirdMenu->id,
                        'name' => (string) $thirdMenu->name,
                        'level' => 3,
                        'menu_type' => (string) ($thirdMenu->menu_type ?? ''),
                    ];
                }
            }
        }

        return $rows;
    }

    public function index(Request $request)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'view')) return $response;

        $actor = $request->attributes->get('groupwise_actor');
        $permissions = $this->permissions($request);
        $hasDashboardRightColumn = $this->hasDashboardRightColumn();

        $profiles = DB::table('tbluserprofilemaster')
            ->select('id', 'name', 'status', 'sort_order')
            ->where('sub_institute_id', $actor->sub_institute_id)
            ->where('status', 1)
            ->orderBy('sort_order')
            ->get();

        $summaryQuery = DB::table('tblgroupwise_rights as rights')
            ->join('tbluserprofilemaster as profile', 'rights.profile_id', '=', 'profile.id')
            ->join('tblmenumaster as menu', 'rights.menu_id', '=', 'menu.id')
            ->select(
                'rights.id',
                'rights.profile_id',
                'profile.name as profile_name',
                'rights.menu_id',
                'menu.name as menu_name',
                'rights.can_view',
                'rights.can_add',
                'rights.can_edit',
                'rights.can_delete'
            );

        $summaryQuery->addSelect($hasDashboardRightColumn
            ? DB::raw('COALESCE(rights.dashboard_right, 0) as dashboard_right')
            : DB::raw('0 as dashboard_right'));

        $summary = $summaryQuery
            ->where('rights.sub_institute_id', $actor->sub_institute_id)
            ->orderBy('profile.sort_order')
            ->orderBy('menu.sort_order')
            ->get();

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => [
                'profiles' => $profiles,
                'summary' => $summary,
                'permissions' => $permissions,
            ],
        ]);
    }

    public function matrix(Request $request, int $profileId)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'view')) return $response;

        $actor = $request->attributes->get('groupwise_actor');
        $profile = $this->activeProfile($request, $profileId);
        if (! $profile) {
            return $this->failure('User profile not found.', 404);
        }

        $rows = $this->matrixRows((int) $actor->sub_institute_id, $profileId);
        $selected = [];
        $hasDashboardRightColumn = $this->hasDashboardRightColumn();

        $rightsQuery = DB::table('tblgroupwise_rights')
            ->select('menu_id', 'can_view', 'can_add', 'can_edit', 'can_delete');

        $rightsQuery->addSelect($hasDashboardRightColumn
            ? DB::raw('COALESCE(dashboard_right, 0) as dashboard_right')
            : DB::raw('0 as dashboard_right'));

        $rightsRows = $rightsQuery
            ->where('sub_institute_id', $actor->sub_institute_id)
            ->where('profile_id', $profileId)
            ->get();

        foreach ($rightsRows as $rightsRow) {
            $selected[(string) $rightsRow->menu_id] = [
                'view' => (bool) $rightsRow->can_view,
                'add' => (bool) $rightsRow->can_add,
                'edit' => (bool) $rightsRow->can_edit,
                'delete' => (bool) $rightsRow->can_delete,
                'dashboardRight' => (bool) $rightsRow->dashboard_right,
            ];
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => [
                'profile' => $profile,
                'rows' => $rows,
                'selected' => $selected,
            ],
        ]);
    }

    public function store(Request $request)
    {
        if ($response = $this->context($request)) return $response;
        if ($response = $this->authorizeAction($request, 'add')) return $response;

        $actor = $request->attributes->get('groupwise_actor');
        $validator = Validator::make($request->all(), [
            'profile_id' => [
                'required',
                'integer',
                Rule::exists('tbluserprofilemaster', 'id')->where(function ($query) use ($actor) {
                    $query->where('sub_institute_id', $actor->sub_institute_id)->where('status', 1);
                }),
            ],
            'selected' => 'required|array',
        ]);
        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $profileId = $request->integer('profile_id');
        $allowedMenuIds = DB::table('tblprofilewise_menu')
            ->where('sub_institute_id', $actor->sub_institute_id)
            ->where('user_profile_id', $profileId)
            ->pluck('menu_id')
            ->map(fn ($value) => (int) $value)
            ->all();

        $allowedLookup = array_fill_keys($allowedMenuIds, true);
        $selected = $request->input('selected', []);
        $hasDashboardRightColumn = $this->hasDashboardRightColumn();

        foreach (array_keys($selected) as $menuId) {
            if (! isset($allowedLookup[(int) $menuId])) {
                return $this->failure('One or more menu selections are invalid for the selected profile.', 422);
            }
        }

        DB::transaction(function () use ($actor, $profileId, $selected, $hasDashboardRightColumn) {
            DB::table('tblgroupwise_rights')
                ->where('sub_institute_id', $actor->sub_institute_id)
                ->where('profile_id', $profileId)
                ->delete();

            foreach ($selected as $menuId => $rights) {
                $menuId = (int) $menuId;
                $row = is_array($rights) ? $rights : [];
                $values = [
                    'can_view' => ! empty($row['view']) ? 1 : 0,
                    'can_add' => ! empty($row['add']) ? 1 : 0,
                    'can_edit' => ! empty($row['edit']) ? 1 : 0,
                    'can_delete' => ! empty($row['delete']) ? 1 : 0,
                    'dashboard_right' => ! empty($row['dashboardRight']) ? 1 : 0,
                ];

                if (! array_filter($values)) {
                    continue;
                }

                $insert = [
                    'menu_id' => $menuId,
                    'profile_id' => $profileId,
                    'sub_institute_id' => $actor->sub_institute_id,
                    'can_view' => $values['can_view'],
                    'can_add' => $values['can_add'],
                    'can_edit' => $values['can_edit'],
                    'can_delete' => $values['can_delete'],
                    'created_at' => now(),
                ];

                if ($hasDashboardRightColumn) {
                    $insert['dashboard_right'] = $values['dashboard_right'];
                }

                DB::table('tblgroupwise_rights')->insert($insert);
            }
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Groupwise Rights Added successfully',
            'data' => [],
        ]);
    }
}
