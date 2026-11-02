<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Services\Access\RoleRightsService;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Roles & Access — menu-wise role permissions, module by module.
 *
 * Built on `tblgroupwise_rights` (a role's rights per menu) and `tbluserprofilemaster` (the
 * roles), through `RoleRightsService`, which enforces the rules the older group-wise rights
 * screen did not: partial saves, mobile rows left alone, parents granted with their children,
 * and a change log with undo. See that class for the details.
 *
 * AUTHENTICATION IS IN THIS CONTROLLER. The token is validated here, the staff-only rule is
 * applied, and the token's school must be the school the request names. Only an Admin or
 * Super Admin may use any of it: this screen decides what everybody else can open.
 *
 * THE ADMIN ROLES CAN BE CHANGED, WITH A CONFIRMATION. This screen is opened by profile name, not by
 * menu rights, so changing the admin role's rights cannot lock an admin out of it; the change does
 * affect every admin, so the client must send `confirm_admin`.
 */
class RoleAccessApiController extends Controller
{
    use GetsJwtToken;

    private const PROTECTED_ROLES = ['admin', 'super admin'];

    private const MAX_CHANGES = 800;

    public function __construct(private readonly RoleRightsService $rights)
    {
    }

    // ------------------------------------------------------------------ roles

    public function roles(Request $request)
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        $tenant = $this->tenant($request);

        $roles = DB::table('tbluserprofilemaster')
            ->where('sub_institute_id', $tenant)->where('status', 1)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'description']);

        $granted = DB::table('tblgroupwise_rights')
            ->where('sub_institute_id', $tenant)
            ->selectRaw('profile_id, COUNT(DISTINCT menu_id) AS n')->groupBy('profile_id')->pluck('n', 'profile_id');
        $users = DB::table('tbluser')
            ->where('sub_institute_id', $tenant)->where('status', 1)
            ->selectRaw('user_profile_id, COUNT(*) AS n')->groupBy('user_profile_id')->pluck('n', 'user_profile_id');

        return $this->ok([
            'roles' => $roles->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => trim((string) $r->name),
                'description' => (string) ($r->description ?? ''),
                'menusGranted' => (int) ($granted[$r->id] ?? 0),
                'menusAssignable' => count($this->rights->allowedMenuIds($tenant, (int) $r->id)),
                'users' => (int) ($users[$r->id] ?? 0),
                'protected' => $this->isProtectedName((string) $r->name),
            ])->values(),
        ]);
    }

    // ------------------------------------------------------------ module tree

    public function modules(Request $request, int $profileId)
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        $tenant = $this->tenant($request);
        $role = $this->role($tenant, $profileId);
        if (! $role) {
            return $this->fail('That role was not found.', 404);
        }

        $allowed = $this->rights->allowedMenuIds($tenant, $profileId);
        $granted = $this->rights->rightsFor($tenant, $profileId);

        $tree = $this->tree($allowed, fn (int $menu) => $this->cell($granted[$menu] ?? null));

        return $this->ok([
            'role' => ['id' => (int) $role->id, 'name' => trim((string) $role->name), 'protected' => $this->isProtectedName((string) $role->name)],
            'modules' => $tree,
        ]);
    }

    // ------------------------------------------------------------------- save

    public function save(Request $request, int $profileId)
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        $tenant = $this->tenant($request);
        $role = $this->role($tenant, $profileId);
        if (! $role) {
            return $this->fail('That role was not found.', 404);
        }
        if ($response = $this->requireAdminConfirmation($request, (string) $role->name)) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'changes' => 'required|array|min:1|max:'.self::MAX_CHANGES,
            'changes.*.menu_id' => 'required|integer|min:1',
            'note' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->messages()->first(), 422);
        }

        $result = $this->rights->apply(
            $tenant, $profileId, (array) $request->input('changes'), $this->actor($request), 'manual', $request->input('note')
        );

        return $this->ok($this->summary($result), $this->saveMessage($result));
    }

    // ------------------------------------------------------------------- copy

    public function copyPreview(Request $request, int $profileId)
    {
        return $this->copy($request, $profileId, false);
    }

    public function copyApply(Request $request, int $profileId)
    {
        return $this->copy($request, $profileId, true);
    }

    private function copy(Request $request, int $profileId, bool $apply)
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        $tenant = $this->tenant($request);

        $validator = Validator::make($request->all(), [
            'source_profile_id' => 'required|integer|min:1',
            'mode' => 'required|in:replace,merge',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->messages()->first(), 422);
        }

        $target = $this->role($tenant, $profileId);
        $source = $this->role($tenant, (int) $request->input('source_profile_id'));
        if (! $target || ! $source) {
            return $this->fail('That role was not found.', 404);
        }
        if ((int) $target->id === (int) $source->id) {
            return $this->fail('Choose a different role to copy from.', 422);
        }
        if ($response = $this->requireAdminConfirmation($request, (string) $target->name)) {
            return $response;
        }

        $mode = (string) $request->input('mode');
        $allowed = $this->rights->allowedMenuIds($tenant, $profileId);
        $from = $this->rights->rightsFor($tenant, (int) $source->id);
        $now = $this->rights->rightsFor($tenant, $profileId);
        $menus = $this->rights->menus();

        $changes = [];
        $notAssignable = 0;

        foreach ($from as $menu => $r) {
            if (! $this->rights->grants($r)) {
                continue;
            }
            if (! isset($allowed[$menu])) {
                $notAssignable++;

                continue;
            }
            $before = $now[$menu] ?? [];
            // Merge only ever adds; replace makes the role match the source. The dashboard and
            // mobile flags travel with the rights.
            $all = [...RoleRightsService::FLAGS, ...RoleRightsService::EXTRAS];
            $after = $mode === 'merge'
                ? array_combine($all, array_map(fn ($f) => ! empty($before[$f]) || ! empty($r[$f]), $all))
                : array_combine($all, array_map(fn ($f) => ! empty($r[$f]), $all));
            $changes[$menu] = ['menu_id' => $menu] + $after;
        }

        if ($mode === 'replace') {
            foreach ($now as $menu => $r) {
                if (! isset($changes[$menu]) && $this->rights->grants($r) && isset($allowed[$menu])) {
                    $changes[$menu] = ['menu_id' => $menu, 'view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'dashboard' => false, 'mobile' => false];
                }
            }
        }

        // Only what actually differs is shown and applied.
        $diff = [];
        foreach ($changes as $menu => $change) {
            $before = $now[$menu] ?? null;
            $after = $this->rights->grants($this->rights->normalise($change)) ? $this->rights->normalise($change) : null;
            if ($this->rights->same($before, $after)) {
                continue;
            }
            $diff[] = ['menuId' => $menu, 'name' => $menus[$menu]['name'] ?? '', 'before' => $this->cell($before), 'after' => $this->cell($after)];
        }

        $base = [
            'target' => ['id' => (int) $target->id, 'name' => trim((string) $target->name)],
            'source' => ['id' => (int) $source->id, 'name' => trim((string) $source->name)],
            'mode' => $mode,
            'differences' => count($diff),
            'granting' => count(array_filter($diff, fn ($d) => ! $this->anyCell($d['before']) && $this->anyCell($d['after']))),
            'revoking' => count(array_filter($diff, fn ($d) => $this->anyCell($d['before']) && ! $this->anyCell($d['after']))),
            'notAssignable' => $notAssignable,
            'changes' => array_slice($diff, 0, 600),
        ];

        if (! $apply) {
            return $this->ok($base);
        }
        if ($diff === []) {
            return $this->ok($base + ['changed' => 0], 'Nothing to change: the two roles already match.');
        }
        if (count($diff) > self::MAX_CHANGES) {
            return $this->fail('That copy changes too many menus at once. Copy in smaller steps.', 422);
        }

        $result = $this->rights->apply(
            $tenant, $profileId,
            array_map(fn ($d) => ['menu_id' => $d['menuId']] + $this->flat($d['after']), $diff),
            $this->actor($request), 'copy',
            'Copied from '.trim((string) $source->name).' ('.$mode.')',
            (int) $source->id
        );

        return $this->ok($base + $this->summary($result), $this->saveMessage($result));
    }

    // -------------------------------------------------------------- effective

    public function users(Request $request)
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        $tenant = $this->tenant($request);
        $term = trim((string) $request->query('q', ''));

        $query = DB::table('tbluser as u')
            ->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->where('u.sub_institute_id', $tenant)->where('u.status', 1)
            ->orderBy('u.first_name')->limit(25);

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $query->where(function ($w) use ($like) {
                $w->whereRaw("CONCAT(TRIM(u.first_name), ' ', TRIM(u.last_name)) LIKE ?", [$like])
                    ->orWhere('u.email', 'like', $like)->orWhere('u.user_name', 'like', $like);
            });
        }

        return $this->ok([
            'users' => $query->get(['u.id', 'u.first_name', 'u.last_name', 'u.email', 'u.user_profile_id', 'p.name as profile_name'])
                ->map(fn ($u) => [
                    'id' => (int) $u->id,
                    'name' => trim($u->first_name.' '.$u->last_name),
                    'email' => (string) ($u->email ?? ''),
                    'roleId' => (int) $u->user_profile_id,
                    'role' => trim((string) $u->profile_name),
                ])->values(),
        ]);
    }

    public function effective(Request $request, int $userId)
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        $tenant = $this->tenant($request);

        $user = DB::table('tbluser as u')
            ->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->where('u.id', $userId)->where('u.sub_institute_id', $tenant)
            ->first(['u.id', 'u.first_name', 'u.last_name', 'u.email', 'u.user_profile_id', 'u.status', 'p.name as profile_name']);
        if (! $user) {
            return $this->fail('That user was not found.', 404);
        }

        $roleId = (int) $user->user_profile_id;
        $group = $this->rights->rightsFor($tenant, $roleId);

        // Individual rows only count for the profile the user holds now, as the sidebar does.
        $individual = [];
        foreach (DB::table('tblindividual_rights')
            ->where('sub_institute_id', $tenant)->where('user_id', $userId)->where('profile_id', $roleId)
            ->get() as $row) {
            $individual[(int) $row->menu_id] = [
                'view' => (bool) $row->can_view, 'add' => (bool) $row->can_add,
                'edit' => (bool) $row->can_edit, 'delete' => (bool) $row->can_delete,
                'dashboard' => false, 'mobile' => (bool) ($row->is_mobile ?? 0),
            ];
        }

        $enabled = array_fill_keys(
            DB::table('tblmenumaster')->where('status', 1)
                ->whereRaw('FIND_IN_SET(?, sub_institute_id)', [$tenant])->pluck('id')->map(fn ($i) => (int) $i)->all(),
            true
        );
        $assignable = $this->rights->allowedMenuIds($tenant, $roleId);
        $union = $group + $individual;

        $stats = ['menus' => 0, 'visible' => 0, 'hidden' => 0, 'viaIndividual' => 0];
        $counted = [];

        $tree = $this->tree($assignable + array_fill_keys(array_keys($individual), true), function (int $menu) use ($group, $individual, $union, $enabled, &$stats, &$counted) {
            $hasGroup = isset($group[$menu]);
            $hasIndividual = isset($individual[$menu]);
            // A menu listed in two modules is one menu: count it once.
            $first = ! isset($counted[$menu]);
            $counted[$menu] = true;

            if (! $hasGroup && ! $hasIndividual) {
                return ['visible' => false, 'source' => 'none', 'rights' => $this->cell(null)['rights'], 'issue' => null];
            }
            if ($first) {
                $stats['menus']++;
            }

            $issue = null;
            if (! isset($enabled[$menu])) {
                $issue = 'This menu is not switched on for the school.';
            } else {
                foreach ($this->rights->ancestors($menu) as $parent) {
                    if (! isset($union[$parent])) {
                        $issue = 'A parent menu is not granted, so this one does not show in the sidebar.';
                        break;
                    }
                }
            }

            $visible = $issue === null;
            if ($first) {
                $stats[$visible ? 'visible' : 'hidden']++;
                if ($hasIndividual) {
                    $stats['viaIndividual']++;
                }
            }

            return [
                'visible' => $visible,
                'source' => $hasGroup && $hasIndividual ? 'both' : ($hasIndividual ? 'individual' : 'role'),
                // For what a user may do inside the screen, an individual row overrides the role's.
                'rights' => $this->cell($hasIndividual ? $individual[$menu] : $group[$menu])['rights'],
                'issue' => $issue,
            ];
        });

        return $this->ok([
            'user' => [
                'id' => (int) $user->id,
                'name' => trim($user->first_name.' '.$user->last_name),
                'email' => (string) ($user->email ?? ''),
                'role' => trim((string) $user->profile_name),
                'roleId' => $roleId,
                'active' => (string) $user->status === '1',
            ],
            'stats' => $stats,
            'modules' => $tree,
        ]);
    }

    /** Menus that can be assigned to a role at this school but that no role and no user can open. */
    public function coverage(Request $request)
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        $tenant = $this->tenant($request);
        $menus = $this->rights->menus();

        $assignable = DB::table('tblprofilewise_menu')->where('sub_institute_id', $tenant)->distinct()->pluck('menu_id')->map(fn ($i) => (int) $i)->all();
        $grantedGroup = DB::table('tblgroupwise_rights')->where('sub_institute_id', $tenant)->distinct()->pluck('menu_id')->map(fn ($i) => (int) $i)->all();
        $grantedUser = DB::table('tblindividual_rights')->where('sub_institute_id', $tenant)->distinct()->pluck('menu_id')->map(fn ($i) => (int) $i)->all();
        $reachable = array_fill_keys(array_merge($grantedGroup, $grantedUser), true);

        $orphans = [];
        foreach ($assignable as $menu) {
            if (isset($reachable[$menu]) || ! isset($menus[$menu]) || $menus[$menu]['level'] !== 3) {
                continue;
            }
            $parent = $menus[$menu]['parent'];
            $orphans[] = ['menuId' => $menu, 'name' => $menus[$menu]['name'], 'module' => $menus[$parent]['name'] ?? ''];
        }
        usort($orphans, fn ($a, $b) => [$a['module'], $a['name']] <=> [$b['module'], $b['name']]);

        return $this->ok(['count' => count($orphans), 'menus' => array_slice($orphans, 0, 300)]);
    }

    // ---------------------------------------------------------------- history

    public function history(Request $request)
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        $tenant = $this->tenant($request);
        $profile = (int) $request->query('profile_id', 0);
        $limit = min(max((int) $request->query('limit', 30), 1), 100);

        $q = DB::table('role_access_changes as c')
            ->leftJoin('tbluserprofilemaster as p', 'p.id', '=', 'c.profile_id')
            ->where('c.sub_institute_id', $tenant)
            ->selectRaw("c.batch_id, MIN(c.created_at) AS at, MAX(c.actor_name) AS actor, MAX(c.profile_id) AS profile_id,
                MAX(p.name) AS profile_name, COUNT(*) AS menus, MAX(c.note) AS note,
                MAX(CASE WHEN c.source IN ('copy','undo') THEN c.source ELSE NULL END) AS special,
                SUM(c.undone_by_batch IS NULL) AS undoable")
            ->groupBy('c.batch_id')->orderByDesc('at')->limit($limit);
        if ($profile > 0) {
            $q->where('c.profile_id', $profile);
        }

        return $this->ok(['batches' => $q->get()->map(fn ($b) => [
            'batchId' => $b->batch_id,
            'at' => (string) $b->at,
            'actor' => (string) ($b->actor ?? ''),
            'roleId' => (int) $b->profile_id,
            'role' => trim((string) ($b->profile_name ?? '')),
            'menus' => (int) $b->menus,
            'kind' => $b->special ?: 'manual',
            'note' => (string) ($b->note ?? ''),
            'undoable' => $b->special !== 'undo' && (int) $b->undoable > 0,
        ])->values()]);
    }

    public function historyDetail(Request $request, string $batchId)
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        $tenant = $this->tenant($request);
        $menus = $this->rights->menus();

        $rows = DB::table('role_access_changes')->where('sub_institute_id', $tenant)->where('batch_id', $batchId)->orderBy('id')->get();
        if ($rows->isEmpty()) {
            return $this->fail('That change was not found.', 404);
        }

        return $this->ok(['entries' => $rows->map(fn ($r) => [
            'menuId' => (int) $r->menu_id,
            'name' => $menus[(int) $r->menu_id]['name'] ?? ('Menu '.$r->menu_id),
            'before' => $this->cell($r->before_rights === null ? null : json_decode((string) $r->before_rights, true)),
            'after' => $this->cell($r->after_rights === null ? null : json_decode((string) $r->after_rights, true)),
            'source' => (string) $r->source,
            'undone' => $r->undone_by_batch !== null,
        ])->values()]);
    }

    public function undo(Request $request, string $batchId)
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        $tenant = $this->tenant($request);

        $role = DB::table('role_access_changes')->where('sub_institute_id', $tenant)->where('batch_id', $batchId)->value('profile_id');
        $profile = $role ? $this->role($tenant, (int) $role) : null;
        if ($profile && ($response = $this->requireAdminConfirmation($request, (string) $profile->name))) {
            return $response;
        }

        $result = $this->rights->undo($tenant, $batchId, $this->actor($request));
        if ($result === null) {
            return $this->fail('There is nothing left to undo in that change.', 404);
        }

        return $this->ok($result, $result['reverted'] > 0
            ? 'Undone: '.$result['reverted'].' menu'.($result['reverted'] === 1 ? '' : 's').' restored'
                .($result['skipped'] ? ', '.count($result['skipped']).' left as they are.' : '.')
            : 'Nothing could be restored: every menu in that change has been changed again since.');
    }

    // =================================================================== helpers

    /**
     * Modules → tabs → menus, in the same layout as the menu bar, limited to `$visibleMenus`.
     *
     * @param  array<int,true>  $visibleMenus  menus to list
     * @param  callable(int):array<string,mixed>  $cell  what to attach to each menu
     * @return array<int, array<string,mixed>>
     */
    private function tree(array $visibleMenus, callable $cell): array
    {
        $categories = DB::table('fees_menu_categories as c')
            ->leftJoin('tblmenumaster as m', 'm.id', '=', 'c.level2_menu_id')
            ->where('c.status', 1)
            ->orderBy('c.module_name')->orderBy('c.sort_order')
            ->get(['c.module_name', 'c.level2_menu_id', 'c.category_key', 'c.label', 'c.sort_order', 'm.name as module_label']);

        $items = DB::table('fees_menu_category_items as i')
            ->join('tblmenumaster as m', 'm.id', '=', 'i.menu_id')
            ->where('i.status', 1)->where('m.status', 1)
            ->orderBy('i.sort_order')->orderBy('i.id')
            ->get(['i.module_name', 'i.category_key', 'm.id', 'm.name', 'm.link', 'm.menu_type'])
            ->groupBy(fn ($r) => $r->module_name.'|'.$r->category_key);

        $modules = [];
        foreach ($categories->groupBy('module_name') as $moduleKey => $rows) {
            $level2 = (int) $rows->first()->level2_menu_id;
            $placed = [];
            $tabs = [];

            foreach ($rows as $cat) {
                $list = [];
                foreach ($items->get($moduleKey.'|'.$cat->category_key, collect()) as $item) {
                    $menu = (int) $item->id;
                    if (! isset($visibleMenus[$menu]) || isset($placed[$menu])) {
                        continue;
                    }
                    $placed[$menu] = true;
                    $list[] = $this->item($menu, $item) + $cell($menu);
                }
                if ($list !== []) {
                    $tabs[] = ['key' => $cat->category_key, 'label' => $cat->label, 'items' => $list];
                }
            }

            // Menus of the module that no tab lists yet.
            $loose = [];
            foreach (DB::table('tblmenumaster')->where('parent_menu_id', $level2)->where('status', 1)->orderBy('sort_order')->get(['id', 'name', 'link', 'menu_type']) as $child) {
                $menu = (int) $child->id;
                if (isset($visibleMenus[$menu]) && ! isset($placed[$menu])) {
                    $loose[] = $this->item($menu, $child) + $cell($menu);
                }
            }
            if ($loose !== []) {
                $tabs[] = ['key' => 'other', 'label' => 'Other menus', 'items' => $loose];
            }

            if ($tabs === []) {
                continue;
            }

            $modules[] = [
                'key' => (string) $moduleKey,
                'label' => trim((string) ($rows->first()->module_label ?: $moduleKey)),
                'level2' => $level2,
                'moduleMenuId' => $level2 > 0 && isset($visibleMenus[$level2]) ? $level2 : null,
                'moduleMenu' => $level2 > 0 && isset($visibleMenus[$level2]) ? $cell($level2) : null,
                'tabs' => $tabs,
            ];
        }

        // The legacy "<module>-report" modules (Admission Report, Exam Report ...) are the same
        // module as "<module>" in every way a person thinks about it, so their tabs are merged into
        // the parent: Admission Report's reports appear under Admission -> Reports.
        $byKey = [];
        foreach ($modules as $index => $module) {
            $byKey[$module['key']] = $index;
        }
        foreach ($modules as $index => $module) {
            if (! str_ends_with($module['key'], '-report')) {
                continue;
            }
            $parent = $byKey[substr($module['key'], 0, -7)] ?? null;
            if ($parent === null) {
                continue;
            }
            foreach ($module['tabs'] as $tab) {
                $at = null;
                foreach ($modules[$parent]['tabs'] as $i => $existing) {
                    if ($existing['key'] === $tab['key']) {
                        $at = $i;
                        break;
                    }
                }
                if ($at === null) {
                    $modules[$parent]['tabs'][] = $tab;

                    continue;
                }
                $have = array_column($modules[$parent]['tabs'][$at]['items'], 'menuId');
                foreach ($tab['items'] as $item) {
                    if (! in_array($item['menuId'], $have, true)) {
                        $modules[$parent]['tabs'][$at]['items'][] = $item;
                    }
                }
            }
            unset($modules[$index]);
        }
        $modules = array_values($modules);

        // Menus the tab definitions do not reach. Some level-2 menus are modules with no tab rows,
        // and some are single screens; without this they could be granted but never seen here.
        $placedAll = [];
        $level2Used = [];
        foreach ($modules as $module) {
            if ($module['level2'] > 0) {
                $level2Used[$module['level2']] = true;
            }
            foreach ($module['tabs'] as $tab) {
                foreach ($tab['items'] as $item) {
                    $placedAll[$item['menuId']] = true;
                }
            }
        }
        $menuMap = $this->rights->menus();
        $linkOf = DB::table('tblmenumaster')->whereIn('id', array_keys($visibleMenus))->pluck('link', 'id');
        $typeOf = DB::table('tblmenumaster')->whereIn('id', array_keys($visibleMenus))->pluck('menu_type', 'id');
        $screens = [];

        foreach (array_keys($visibleMenus) as $menu) {
            if (($menuMap[$menu]['level'] ?? 0) !== 2 || isset($level2Used[$menu])) {
                continue;
            }
            $children = [];
            foreach ($menuMap as $childId => $child) {
                if ($child['parent'] === $menu && isset($visibleMenus[$childId]) && ! isset($placedAll[$childId])) {
                    $children[] = [
                        'menuId' => $childId, 'name' => $child['name'],
                        'link' => (string) ($linkOf[$childId] ?? ''), 'type' => (string) ($typeOf[$childId] ?? ''),
                    ] + $cell($childId);
                    $placedAll[$childId] = true;
                }
            }

            if ($children !== []) {
                $modules[] = [
                    'key' => 'menu-'.$menu, 'label' => $menuMap[$menu]['name'], 'level2' => $menu,
                    'moduleMenuId' => $menu, 'moduleMenu' => $cell($menu),
                    'tabs' => [['key' => 'other', 'label' => 'Menus', 'items' => $children]],
                ];
                $level2Used[$menu] = true;

                continue;
            }

            // A level-2 menu with nothing under it is a screen in its own right.
            if (! isset($placedAll[$menu])) {
                $group = $menuMap[$menu]['parent'];
                $screens[$group][] = [
                    'menuId' => $menu, 'name' => $menuMap[$menu]['name'],
                    'link' => (string) ($linkOf[$menu] ?? ''), 'type' => (string) ($typeOf[$menu] ?? ''),
                ] + $cell($menu);
                $placedAll[$menu] = true;
            }
        }
        // Level-3 menus whose parent menu is not itself listed (it is not switched on for the school),
        // grouped under the parent's name so they can still be seen and set.
        $stray = [];
        foreach (array_keys($visibleMenus) as $menu) {
            if (($menuMap[$menu]['level'] ?? 0) === 3 && ! isset($placedAll[$menu])) {
                $stray[$menuMap[$menu]['parent']][] = $menu;
            }
        }
        foreach ($stray as $parentId => $menuIds) {
            $items = [];
            foreach ($menuIds as $menu) {
                $placedAll[$menu] = true;
                $items[] = [
                    'menuId' => $menu, 'name' => $menuMap[$menu]['name'],
                    'link' => (string) ($linkOf[$menu] ?? ''), 'type' => (string) ($typeOf[$menu] ?? ''),
                ] + $cell($menu);
            }
            $modules[] = [
                'key' => 'parent-'.$parentId, 'label' => $menuMap[$parentId]['name'] ?? 'Other screens', 'level2' => $parentId,
                'moduleMenuId' => null, 'moduleMenu' => null,
                'tabs' => [['key' => 'other', 'label' => 'Menus', 'items' => $items]],
            ];
        }

        foreach ($screens as $groupId => $items) {
            // Screens that sit directly under a root menu are named after it: "Institute (Master)",
            // "Exam (Master)" and so on, with the tab called Master or Reports to match.
            $rootType = strtoupper((string) ($typeOf[$groupId] ?? ''));
            $modules[] = [
                'key' => 'screens-'.$groupId, 'label' => $menuMap[$groupId]['name'] ?? 'Other screens', 'level2' => 0,
                'moduleMenuId' => null, 'moduleMenu' => null, 'groupId' => $groupId,
                'tabs' => [[
                    'key' => 'other',
                    'label' => $rootType === 'MASTER' ? 'Master' : ($rootType === 'REPORT' ? 'Reports' : 'Screens'),
                    'items' => $items,
                ]],
            ];
        }

        // Level-4 menus (the screens under "AI Administration" in Talent Management, for one) sit
        // under a level-3 menu this screen lists as a row; they are listed right after it, named
        // "Parent > Child", so nothing granted is out of sight.
        $children4 = [];
        foreach (array_keys($visibleMenus) as $menu) {
            if (($menuMap[$menu]['level'] ?? 0) === 4 && ! isset($placedAll[$menu])) {
                $children4[$menuMap[$menu]['parent']][] = $menu;
            }
        }
        if ($children4 !== []) {
            foreach ($modules as $mi => $module) {
                foreach ($module['tabs'] as $ti => $tab) {
                    $rebuilt = [];
                    foreach ($tab['items'] as $item) {
                        $rebuilt[] = $item;
                        foreach ($children4[$item['menuId']] ?? [] as $child) {
                            if (isset($placedAll[$child])) {
                                continue;
                            }
                            $placedAll[$child] = true;
                            $rebuilt[] = [
                                'menuId' => $child,
                                'name' => $item['name'].' › '.$menuMap[$child]['name'],
                                'link' => (string) ($linkOf[$child] ?? ''),
                                'type' => (string) ($typeOf[$child] ?? ''),
                            ] + $cell($child);
                        }
                    }
                    $modules[$mi]['tabs'][$ti]['items'] = $rebuilt;
                }
            }
        }

        // Every module sits under one of the main sidebar modules (Institute ERP, LMS + PAL, People &
        // Competency, Enterprise Brain, Career Counseling). Those are the level-1 menus that are not a
        // "(Master)" or Reports root and have something under them. The "(Master)" and Reports roots
        // (Fees, Exam, Hostel, Inventory masters, Reports ...) hold parts of Institute ERP, so their
        // modules are listed under it rather than as groups of their own.
        $mains = DB::table('tblmenumaster')->where('level', 1)->where('status', 1)
            ->where(fn ($q) => $q->whereNull('menu_type')->orWhereNotIn('menu_type', ['MASTER', 'REPORT']))
            ->get(['id', 'name', 'sort_order'])
            ->filter(fn ($r) => DB::table('tblmenumaster')->where('parent_menu_id', $r->id)->where('status', 1)->exists())
            ->values();
        $preferred = ['institute erp', 'lms + pal', 'people & competency', 'enterprise brain', 'career counseling'];
        $mains = $mains->sortBy(function ($r) use ($preferred) {
            $at = array_search(strtolower(trim($r->name)), $preferred, true);

            return [$at === false ? 99 : $at, (int) $r->sort_order, (int) $r->id];
        })->values();
        $mainIndex = [];
        foreach ($mains as $position => $r) {
            $mainIndex[(int) $r->id] = $position;
        }
        $defaultMain = $mains->first(fn ($r) => strtolower(trim($r->name)) === 'institute erp') ?? $mains->first();

        $rootOf = function (int $menu) use ($menuMap): int {
            $guard = 0;
            while ($menu > 0 && isset($menuMap[$menu]) && $menuMap[$menu]['level'] > 1 && $guard++ < 6) {
                $menu = $menuMap[$menu]['parent'];
            }

            return $menu;
        };

        foreach ($modules as $i => $module) {
            $anchor = (int) ($module['groupId'] ?? $module['level2'] ?? 0);
            $root = $anchor > 0 ? $rootOf($anchor) : 0;
            if (! isset($mainIndex[$root]) && $defaultMain) {
                $root = (int) $defaultMain->id;
            }
            $modules[$i]['group'] = $mains->firstWhere('id', $root)->name ?? 'Other';
            $modules[$i]['groupOrder'] = $mainIndex[$root] ?? 99999;
            unset($modules[$i]['groupId'], $modules[$i]['level2']);
        }

        // "Other screens" from several roots can land in one group: keep a single list per group.
        $screensAt = [];
        foreach ($modules as $i => $module) {
            // Only the unnamed ones are merged; a root's screens keep the root's name.
            if (! str_starts_with($module['key'], 'screens-') || $module['label'] !== 'Other screens') {
                continue;
            }
            if (! isset($screensAt[$module['group']])) {
                $screensAt[$module['group']] = $i;

                continue;
            }
            $first = $screensAt[$module['group']];
            $modules[$first]['tabs'][0]['items'] = array_merge($modules[$first]['tabs'][0]['items'], $module['tabs'][0]['items']);
            unset($modules[$i]);
        }
        $modules = array_values($modules);

        // How many modules list each menu, so a shared menu can be flagged.
        $count = [];
        foreach ($modules as $module) {
            foreach ($module['tabs'] as $tab) {
                foreach ($tab['items'] as $item) {
                    $count[$item['menuId']] = ($count[$item['menuId']] ?? 0) + 1;
                }
            }
        }
        foreach ($modules as &$module) {
            foreach ($module['tabs'] as &$tab) {
                foreach ($tab['items'] as &$item) {
                    $item['inModules'] = $count[$item['menuId']] ?? 1;
                }
            }
        }
        unset($module, $tab, $item);

        usort($modules, fn ($a, $b) => [$a['groupOrder'], strtolower($a['group']), strtolower($a['label'])] <=> [$b['groupOrder'], strtolower($b['group']), strtolower($b['label'])]);

        return $modules;
    }

    private function item(int $menu, object $row): array
    {
        return [
            'menuId' => $menu,
            'name' => trim((string) $row->name),
            'link' => (string) $row->link,
            'type' => (string) ($row->menu_type ?? ''),
        ];
    }

    /** @return array{rights:array<string,bool>} */
    private function cell(?array $rights): array
    {
        return ['rights' => [
            'view' => ! empty($rights['view']),
            'add' => ! empty($rights['add']),
            'edit' => ! empty($rights['edit']),
            'delete' => ! empty($rights['delete']),
            'dashboard' => ! empty($rights['dashboard']),
            'mobile' => ! empty($rights['mobile']),
        ]];
    }

    private function anyCell(array $cell): bool
    {
        return in_array(true, $cell['rights'], true);
    }

    /** @return array<string,bool> */
    private function flat(array $cell): array
    {
        return $cell['rights'];
    }

    /** @param array<string,mixed> $result */
    private function summary(array $result): array
    {
        return [
            'batchId' => $result['batchId'],
            'changed' => $result['changed'],
            'autoGranted' => array_map(fn ($id) => ['menuId' => $id, 'name' => $this->rights->menus()[$id]['name'] ?? ''], $result['autoGranted']),
            'rejected' => $result['rejected'],
        ];
    }

    /** @param array<string,mixed> $result */
    private function saveMessage(array $result): string
    {
        if ($result['changed'] === 0) {
            return 'Nothing changed.';
        }
        $text = $result['changed'].' menu'.($result['changed'] === 1 ? '' : 's').' updated';
        if ($result['autoGranted'] !== []) {
            $text .= ' (including '.count($result['autoGranted']).' parent menu'.(count($result['autoGranted']) === 1 ? '' : 's').' so they show in the sidebar)';
        }

        return $text.'.';
    }

    private function role(int $tenant, int $profileId): ?object
    {
        return DB::table('tbluserprofilemaster')
            ->where('sub_institute_id', $tenant)->where('id', $profileId)->where('status', 1)
            ->first(['id', 'name']);
    }

    /**
     * Changing the Admin or Super Admin role changes what every admin at the school can open, so
     * it is allowed but has to be confirmed explicitly (`confirm_admin`). A client that forgets
     * gets a refusal, not a silent change. Returns a response when it must stop.
     */
    private function requireAdminConfirmation(Request $request, string $roleName): ?JsonResponse
    {
        if ($this->isProtectedName($roleName) && ! $request->boolean('confirm_admin')) {
            return $this->fail('This is an Admin role: the change affects every admin at the school. Confirm to continue.', 409);
        }

        return null;
    }

    private function isProtectedName(string $name): bool
    {
        return in_array(strtolower(trim($name)), self::PROTECTED_ROLES, true);
    }

    private function tenant(Request $request): int
    {
        return (int) $request->attributes->get('role_access_actor')->sub_institute_id;
    }

    /** @return array{id:?int,name:?string} */
    private function actor(Request $request): array
    {
        $actor = $request->attributes->get('role_access_actor');

        return ['id' => (int) $actor->id, 'name' => trim((string) $actor->name)];
    }

    private function ok(array $data, string $message = 'Success'): JsonResponse
    {
        return response()->json(['status_code' => 1, 'message' => $message, 'data' => $data]);
    }

    private function fail(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['status_code' => 0, 'message' => $message, 'errors' => null, 'data' => []], $status);
    }

    /**
     * Validate the token, apply the staff-only rule, bind the token to the school and the user
     * named in the request, and require an Admin or Super Admin. Returns a response when the
     * request must stop, null when it may continue.
     */
    protected function context(Request $request): ?JsonResponse
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
            return $this->fail($validator->messages()->first());
        }

        $token = preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization'));
        $parts = explode('.', $token);
        $payload = [];
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')) ?: '{}', true) ?: [];
        }

        // A student or parent token's `id` is a tblstudent id and must never be read as a tbluser id.
        if (! empty($payload['is_student']) || ! empty($payload['student_id'])) {
            return response()->json(['status_code' => 2, 'message' => 'Not available to this account.', 'data' => []], 403);
        }

        $actorId = (int) ($payload['id'] ?? 0);
        $tenantId = (int) ($payload['sub_institute_id'] ?? 0);
        if ($actorId !== $request->integer('user_id') || $tenantId !== $request->integer('sub_institute_id')) {
            return response()->json(['status_code' => 2, 'message' => 'Token context does not match the request.', 'data' => []], 403);
        }

        $actor = DB::table('tbluser as user')
            ->join('tbluserprofilemaster as profile', 'profile.id', '=', 'user.user_profile_id')
            ->where('user.id', $actorId)->where('user.sub_institute_id', $tenantId)->where('user.status', 1)
            ->first(['user.id', 'user.sub_institute_id', 'user.first_name', 'user.last_name', 'profile.name as profile_name']);

        if (! $actor) {
            return response()->json(['status_code' => 2, 'message' => 'Active user context was not found.', 'data' => []], 403);
        }
        if (! $this->isProtectedName((string) $actor->profile_name)) {
            return $this->fail('Only an Admin can manage roles and access.', 403);
        }

        $actor->name = trim($actor->first_name.' '.$actor->last_name);
        $request->attributes->set('role_access_actor', $actor);

        return null;
    }
}
