<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The category navigation feed for every module that is not Fees or
 * Teach/Learn — one endpoint rather than 62 near-identical controllers.
 *
 * Fees and Teach/Learn keep their own routes and their own thin subclasses
 * because their frontend clients already call them by name and there is no
 * reason to break that. Everything they do is inherited from
 * AbstractMenuCategoryApiController, which is also what serves this class, so
 * all three answer from exactly one implementation of the visibility rules.
 *
 * Two actions:
 *
 *  - index()    the categories for one module, named by `module_name` or, more
 *               reliably, by the level-2 menu id the bar is being drawn for.
 *  - registry() every module that has a bar at all. The frontend needs this to
 *               answer "does the menu the user just selected have a category
 *               bar, and under which key?" without shipping a hardcoded list of
 *               62 modules that would drift from the database the first time
 *               someone seeded or retired one.
 */
class ModuleMenuCategoryApiController extends AbstractMenuCategoryApiController
{
    /**
     * The module named by the request.
     *
     * `level2_menu_id` is preferred over `module_name` because it is the only
     * unambiguous identifier the caller has: two active level-2 menus are both
     * named "Task Management", so anything derived from a label can point at
     * the wrong module. `module_name` remains accepted for a caller that
     * already knows the slug, such as a category page loading from its own URL.
     */
    protected function resolveModuleName(Request $request): string
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            return '';
        }

        $level2MenuId = (int) $request->input('level2_menu_id', 0);

        if ($level2MenuId > 0 && Schema::hasColumn('fees_menu_categories', 'level2_menu_id')) {
            $resolved = DB::table('fees_menu_categories')
                ->where('level2_menu_id', $level2MenuId)
                ->where('status', 1)
                ->value('module_name');

            if (is_string($resolved) && $resolved !== '') {
                return $resolved;
            }
        }

        $moduleName = trim((string) $request->input('module_name', ''));

        if ($moduleName === '') {
            return '';
        }

        // Only a module that actually has rows, so an unknown or misspelled
        // slug returns an empty bar instead of being taken at its word.
        $exists = DB::table('fees_menu_categories')
            ->where('module_name', $moduleName)
            ->where('status', 1)
            ->exists();

        return $exists ? $moduleName : '';
    }

    /**
     * Every module with a category bar, as
     * `[{module_name, level2_menu_id, label, base_route, routes}]`.
     *
     * `label` is the level-2 menu's own name, read from tblmenumaster rather
     * than stored again here, so renaming a menu renames its module everywhere
     * at once.
     *
     * `base_route` and `routes` are what let the frontend recognise a deep link
     * as belonging to a module on a cold page load, before any level-2
     * selection has been made. Both are needed because a module's category
     * routes do not all have to share a prefix: Teach/Learn keeps eight under
     * /teach-learn but points Onboarding at /onboarding/lms and Process Builder
     * at /general/add_process, which are pre-existing standalone pages. Prefix
     * matching alone would miss those two; `routes` catches them exactly.
     *
     * Deliberately not filtered by the caller's rights: this answers which
     * modules have a bar configured, not which screens a user may open. The
     * screens themselves are still filtered per user by index(), so an
     * unauthorised user sees an empty bar rather than no bar.
     */
    public function registry(Request $request): JsonResponse
    {
        if (! Schema::hasTable('fees_menu_categories')
            || ! Schema::hasColumn('fees_menu_categories', 'level2_menu_id')) {
            return response()->json(['status' => 1, 'data' => ['modules' => []]]);
        }

        $rows = DB::table('fees_menu_categories as c')
            ->leftJoin('tblmenumaster as m', 'm.id', '=', 'c.level2_menu_id')
            ->where('c.status', 1)
            ->orderBy('c.module_name')
            ->orderBy('c.sort_order')
            ->get(['c.module_name', 'c.level2_menu_id', 'c.route', 'm.name as label']);

        $modules = [];

        foreach ($rows as $row) {
            $moduleName = (string) $row->module_name;

            if (! isset($modules[$moduleName])) {
                $modules[$moduleName] = [
                    'module_name' => $moduleName,
                    'level2_menu_id' => $row->level2_menu_id === null ? null : (int) $row->level2_menu_id,
                    'label' => (string) ($row->label ?? ''),
                    'category_count' => 0,
                    'routes' => [],
                    'bases' => [],
                ];
            }

            // Counted over every active category, including those with no
            // route, because the sidebar uses this to decide whether the module
            // has a level-3 bar at all — not to decide what to link to.
            $modules[$moduleName]['category_count']++;

            $route = $this->normalizePath((string) ($row->route ?? ''));

            if ($route === '') {
                continue;
            }

            $modules[$moduleName]['routes'][] = $route;

            $base = $this->baseRoute($route);
            if ($base !== '') {
                $modules[$moduleName]['bases'][$base] = ($modules[$moduleName]['bases'][$base] ?? 0) + 1;
            }
        }

        $payload = [];

        foreach ($modules as $module) {
            $bases = $module['bases'];

            // The dominant base, not the first or the alphabetically smallest.
            // Teach/Learn's outlier routes (/onboarding/lms, /general/add_process)
            // would otherwise win and hand it every /general/* page in the app.
            arsort($bases);

            $payload[] = [
                'module_name' => $module['module_name'],
                'level2_menu_id' => $module['level2_menu_id'],
                'label' => $module['label'],
                'category_count' => $module['category_count'],
                'base_route' => (string) (array_key_first($bases) ?? ''),
                'routes' => array_values(array_unique($module['routes'])),
            ];
        }

        usort($payload, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return response()->json([
            'status' => 1,
            'data' => ['modules' => $payload],
        ]);
    }

    /** Path only, lower-cased, no query string and no trailing slash. */
    private function normalizePath(string $route): string
    {
        $path = strtolower(trim(parse_url($route, PHP_URL_PATH) ?: ''));
        $path = rtrim($path, '/');

        return $path === '' ? '' : $path;
    }

    /**
     * The module prefix of a category route: '/fees/master-setup' -> '/fees',
     * '/modules/inventory/reports' -> '/modules/inventory'.
     *
     * A category route is '<base…>/<category-key>', so the base is everything
     * but the last segment.
     */
    private function baseRoute(string $route): string
    {
        $segments = array_values(array_filter(explode('/', trim($route, '/')), fn ($s) => $s !== ''));

        array_pop($segments);

        return $segments === [] ? '' : '/'.implode('/', $segments);
    }
}
