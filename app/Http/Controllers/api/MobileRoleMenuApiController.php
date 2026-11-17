<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The menus a user's role (plus their own individual rights) has been given for the mobile app
 * from web "Roles & Access" -> the Mobile column (tblgroupwise_rights.is_mobile / tblindividual_rights.is_mobile).
 *
 * /api/mobile/bootstrap's `menu` reads the separate mobile_homescreen tables, which that screen
 * never writes, so a role ticked "Mobile" there showed nothing in the app. This endpoint reads the
 * rights themselves. Identity comes only from the verified JWT (api.session).
 */
class MobileRoleMenuApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $session = $request->session();

        return response()->json([
            'success' => true,
            'message' => 'Mobile role menu fetched successfully.',
            'data' => self::forSession(
                (int) $session->get('user_id'),
                (int) $session->get('sub_institute_id'),
                (int) $session->get('user_profile_id'),
                (bool) $session->get('is_student'),
            ),
        ]);
    }

    private static function resolvePath(string $link): ?string
    {
        $link = trim($link);
        if ($link === '' || str_starts_with($link, 'javascript') || str_starts_with($link, '#')) {
            return null;
        }
        if (str_contains($link, '/')) {
            return '/' . ltrim($link, '/');
        }
        if (\Illuminate\Support\Facades\Route::has($link)) {
            try {
                return route($link, [], false);
            } catch (\Throwable $e) {
                return null; // needs parameters
            }
        }

        return null;
    }

    /**
     * @return array{menus: array<int,array<string,mixed>>, tree: array<int,array<string,mixed>>}
     */
    public static function forSession(int $userId, int $tenant, int $profileId, bool $isStudent): array
    {
        // Same union the web sidebar uses: the role's rows OR the user's own rows.
        // Every role, Admin included, gets exactly the menus ticked Mobile in Roles & Access.
        $isAdmin = false;

        $ids = DB::table('tblgroupwise_rights')
            ->where('sub_institute_id', $tenant)->where('profile_id', $profileId)
            ->when(! $isAdmin, fn ($q) => $q->where('is_mobile', 1))
            ->pluck('menu_id')->all();

        // tbluser and tblstudent ids overlap, so a student never matches individual rows.
        if (! $isStudent) {
            $ids = array_merge($ids, DB::table('tblindividual_rights')
                ->where('sub_institute_id', $tenant)->where('user_id', $userId)
                ->where('profile_id', $profileId)
                ->when(! $isAdmin, fn ($q) => $q->where('is_mobile', 1))
                ->pluck('menu_id')->all());
        }

        $granted = array_fill_keys(array_map('intval', $ids), true);
        if (! $granted) {
            return ['menus' => [], 'tree' => []];
        }

        $all = DB::table('tblmenumaster')->where('status', 1)
            ->whereRaw('FIND_IN_SET(?, sub_institute_id)', [$tenant])
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'name', 'menu_title', 'parent_menu_id', 'level', 'sort_order', 'link', 'icon', 'menu_type'])
            ->keyBy('id');

        $shape = fn ($m, bool $enabled) => [
            'id' => (int) $m->id,
            // `name` is the screen's own name; `menu_title` is only the group label shared by siblings.
            'title' => trim((string) ($m->name ?: $m->menu_title)),
            'group_title' => trim((string) $m->menu_title),
            'name' => trim((string) $m->name),
            'parent_menu_id' => (int) $m->parent_menu_id,
            'level' => (int) $m->level,
            'sort_order' => (int) $m->sort_order,
            'link' => $m->link,
            // The Laravel URL that serves this screen's data (type=API gives JSON). Menu links are
            // either a path ("/fees/collect") or a route name ("circular.index"); names are resolved
            // here so the app never needs the route table. Null when nothing resolves (Next.js-only pages).
            'path' => self::resolvePath((string) $m->link),
            'icon' => $m->icon,
            'menu_type' => $m->menu_type,
            'mobile_enabled' => $enabled,
        ];

        $menus = [];
        $nodes = [];
        foreach ($granted as $id => $_) {
            // Parents come along so the app can nest a child under its module, but only the
            // ticked menus are `mobile_enabled`.
            for ($cur = $id, $guard = 0; $cur && isset($all[$cur]) && $guard < 6; $cur = (int) $all[$cur]->parent_menu_id, $guard++) {
                $nodes[$cur] ??= $shape($all[$cur], isset($granted[$cur]));
            }
            if (isset($all[$id])) {
                $menus[] = $shape($all[$id], true);
            }
        }

        $byOrder = fn ($a, $b) => [$a['sort_order'], $a['id']] <=> [$b['sort_order'], $b['id']];
        usort($menus, $byOrder);

        $children = [];
        foreach ($nodes as $node) {
            $children[$node['parent_menu_id']][] = $node;
        }
        $build = function (int $parent) use (&$build, &$children, $byOrder): array {
            $list = $children[$parent] ?? [];
            usort($list, $byOrder);
            foreach ($list as &$n) {
                $n['children'] = $build($n['id']);
            }

            return $list;
        };

        // Roots are nodes whose parent is not itself in the set (parent 0, or a hidden parent).
        $roots = [];
        foreach ($nodes as $node) {
            if (! isset($nodes[$node['parent_menu_id']])) {
                $roots[] = $node['parent_menu_id'];
            }
        }
        $tree = [];
        foreach (array_unique($roots) as $rootParent) {
            $tree = array_merge($tree, $build($rootParent));
        }
        usort($tree, $byOrder);

        return ['menus' => $menus, 'tree' => $tree];
    }
}
