<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Category-navigation feed shared by every module that groups its *existing*
 * tblmenumaster menus into a level-3 category bar (Fees originally, now also
 * Teach/Learn). See FeesMenuCategoryApiController for the full rationale
 * behind why these categories are data rather than tblmenumaster rows.
 *
 * `fees_menu_categories` / `fees_menu_category_items` are shared across
 * modules via their `module_name` column (see
 * 2026_09_10_150000_add_module_name_to_fees_menu_category_tables.php) — a
 * subclass names its module through moduleName() and every query here scopes
 * to it, so modules can never see or collide with each other's rows.
 */
abstract class AbstractMenuCategoryApiController extends Controller
{
    /**
     * The category an unmapped menu lands in when its own name says nothing
     * about it. A screen that announces nothing about itself is a day-to-day
     * screen.
     */
    private const DEFAULT_CATEGORY_KEY = 'operations';

    /**
     * Which module's rows this request wants.
     *
     * Takes the request because a module is no longer always a property of the
     * class: the per-module subclasses answer with a constant, while
     * ModuleMenuCategoryApiController serves all 62 remaining modules from one
     * endpoint and has to read it off the request. Returning '' means the
     * module could not be resolved, and index() answers with an empty bar
     * rather than guessing.
     */
    abstract protected function resolveModuleName(Request $request): string;

    public function index(Request $request): JsonResponse
    {
        $subInstituteId = (string) $request->input('sub_institute_id', '');
        $userId = (string) $request->input('user_id', '');

        if ($subInstituteId === '' || $userId === '') {
            return response()->json([
                'status' => 0,
                'message' => 'sub_institute_id and user_id are required.',
            ], 422);
        }

        if (! Schema::hasTable('fees_menu_categories') || ! Schema::hasTable('fees_menu_category_items')) {
            return response()->json(['status' => 1, 'data' => ['categories' => []]]);
        }

        $moduleName = $this->resolveModuleName($request);

        if ($moduleName === '') {
            return response()->json(['status' => 1, 'data' => ['categories' => []]]);
        }

        // `onboarding_module_key` arrived with the onboarding rollout, so it is
        // selected only where it exists rather than making this feed — which
        // every module's navigation depends on — fail on an installation that
        // has not run that migration yet.
        $hasOnboardingKey = Schema::hasColumn('fees_menu_categories', 'onboarding_module_key');
        // Same treatment for the platform-services column, for the same reason.
        // It was introduced as `workflow_module_key` and renamed once Scheduler
        // needed the same mapping, so both spellings are read and served under
        // the one name the frontend knows.
        $platformKeyColumn = match (true) {
            Schema::hasColumn('fees_menu_categories', 'platform_module_key') => 'platform_module_key',
            Schema::hasColumn('fees_menu_categories', 'workflow_module_key') => 'workflow_module_key',
            default => '',
        };

        // The audit trail's prefixes, from the same rollout as the Schedular
        // category. Same guard, same reason.
        $hasAuditKeys = Schema::hasColumn('fees_menu_categories', 'audit_module_keys');

        $categoryRows = DB::table('fees_menu_categories')
            ->where('module_name', $moduleName)
            ->where('status', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(array_merge(
                ['category_key', 'label', 'description', 'route'],
                $hasOnboardingKey ? ['onboarding_module_key'] : [],
                $platformKeyColumn !== '' ? [$platformKeyColumn.' as platform_module_key'] : [],
                $hasAuditKeys ? ['audit_module_keys'] : []
            ));

        if ($categoryRows->isEmpty()) {
            return response()->json(['status' => 1, 'data' => ['categories' => []]]);
        }

        $itemsByCategory = $this->visibleItemsByCategory(
            $moduleName,
            $subInstituteId,
            $userId,
            (string) $request->input('user_profile_name', ''),
            $categoryRows->pluck('category_key')->map(fn ($key) => (string) $key)->all()
        );

        $categories = $categoryRows->map(fn ($category) => [
            'key' => (string) $category->category_key,
            'label' => (string) $category->label,
            'description' => (string) ($category->description ?? ''),
            // The category's own page. The level-3 bar links here; the page
            // itself renders the items below as its horizontal tab bar.
            'route' => (string) ($category->route ?? ''),
            // Set on the Onboarding category only: the onboarding journey this
            // module shows, from onboarding_module.module_key. Empty means the
            // category renders its menus like any other — or, for Onboarding,
            // that this bar has no single journey to show.
            'onboarding_module_key' => (string) ($category->onboarding_module_key ?? ''),
            // Set on the Workflow and Schedular categories: the
            // config/platform_services.php module whose approval points and
            // scheduled tasks this bar configures. Empty means the registry
            // declares no such module, and the category page says so rather than
            // pinning a console to a neighbouring module's records.
            'platform_module_key' => (string) ($category->platform_module_key ?? ''),
            // Set on the Audit Trail category only: the access_log_route.module
            // prefixes this bar's screens write. A list, because one bar's
            // screens can sit under several — the Exam bar logs under both
            // 'exam' and 'result'. Empty means this module's screens never reach
            // the middleware that writes the log, and the page says so.
            'audit_module_keys' => $this->auditKeys($category->audit_module_keys ?? null),
            'items' => $itemsByCategory[$category->category_key] ?? [],
        ])->all();

        return response()->json([
            'status' => 1,
            'data' => ['categories' => $categories],
        ]);
    }

    /**
     * The audit prefixes as a list, from the comma-separated column.
     *
     * Normalised here rather than in the browser so every caller sees the same
     * shape: trimmed, lower-cased, no blanks from a trailing comma.
     *
     * @return list<string>
     */
    private function auditKeys(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $keys = array_filter(
            array_map(fn ($key) => strtolower(trim($key)), explode(',', $value)),
            fn ($key) => $key !== ''
        );

        return array_values(array_unique($keys));
    }

    /**
     * Every one of this module's menus the caller may actually see, grouped
     * by category key.
     *
     * Two sources, in this order:
     *
     *  1. `fees_menu_category_items` — the configured rows, in their configured
     *     sort order. These are authoritative: they decide placement, they
     *     decide order, and they are how one module's bar borrows a screen that
     *     hangs under another module's level-2 menu.
     *  2. autoDiscoveredItems() — everything else the menu tree hangs under this
     *     module that step 1 said nothing about, appended after. This is what
     *     makes a menu added or moved in the database show up without a code
     *     change; see that method for why it is needed.
     *
     * The join to tblmenumaster is what applies the visibility rules — the
     * configuration tables only say where a menu belongs, never whether it is
     * allowed to be seen — and both sources go through the same ones.
     *
     * @param  list<string>  $categoryKeys  this module's own category keys, so a
     *                                      discovered menu can only land in a
     *                                      category the bar actually has.
     * @return array<string,list<array{id:int,label:string,link:string}>>
     */
    private function visibleItemsByCategory(
        string $moduleName,
        string $subInstituteId,
        string $userId,
        string $userProfileName,
        array $categoryKeys = []
    ): array {
        $permittedMenuIds = $this->permittedMenuIds($subInstituteId, $userId, $userProfileName);
        if ($permittedMenuIds === []) {
            return [];
        }

        // The configured rows first.
        $rows = DB::table('fees_menu_category_items as c')
            ->join('tblmenumaster as m', 'm.id', '=', 'c.menu_id')
            ->where('c.module_name', $moduleName)
            ->where('c.status', 1)
            ->where('m.status', 1)
            ->whereIn('m.id', $permittedMenuIds)
            ->whereRaw('FIND_IN_SET(?, m.sub_institute_id)', [$subInstituteId])
            ->orderBy('c.sort_order')
            ->orderBy('c.id')
            ->get(['c.category_key', 'm.id', 'm.name', 'm.link']);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->category_key][] = [
                'id' => (int) $row->id,
                'label' => (string) $row->name,
                'link' => (string) $row->link,
            ];
        }

        // Then the menus the tree has that the mapping table does not, appended
        // after the configured ones so a hand-placed order is never disturbed.
        $discovered = $this->autoDiscoveredItems(
            $moduleName,
            $this->level2MenuId($moduleName),
            $subInstituteId,
            $permittedMenuIds,
            $categoryKeys
        );

        foreach ($discovered as $categoryKey => $items) {
            $grouped[$categoryKey] = array_merge($grouped[$categoryKey] ?? [], $items);
        }

        return $grouped;
    }

    /**
     * This module's level-2 menu, or 0 when it has none.
     *
     * `level2_menu_id` arrived with 2026_09_17_100000 and is read only where it
     * exists, so an installation that has not run that migration keeps the
     * behaviour it has today — configured rows and nothing else — rather than
     * this feed failing outright.
     */
    private function level2MenuId(string $moduleName): int
    {
        if (! Schema::hasColumn('fees_menu_categories', 'level2_menu_id')) {
            return 0;
        }

        $id = (int) DB::table('fees_menu_categories')
            ->where('module_name', $moduleName)
            ->where('status', 1)
            ->whereNotNull('level2_menu_id')
            // Ordered so the answer cannot depend on row order. Every row of a
            // module carries the same id today; if one ever did not, picking
            // the same one every time beats flapping between two bars.
            ->orderBy('id')
            ->value('level2_menu_id');

        return $id > 0 ? $id : 0;
    }

    /**
     * The menus that belong to this module by the menu tree, but that
     * `fees_menu_category_items` says nothing about.
     *
     * WHY THIS EXISTS. The mapping table was written once, by
     * 2026_09_17_100001, from the tree as it stood that day. The tree has moved
     * since and keeps moving: a menu added under a module afterwards has no row
     * here, and neither does one re-parented into the module from somewhere
     * else. The Student bar is the clearest case — Student I-card, Student
     * Health, Student Vaccination, Student Height Weight, Student Infirmary,
     * Student Certificate and Student Request were all moved under Student once
     * their own level-2 menus were switched off, and so were reachable from no
     * bar at all. Without this the only cure is another seeding migration every
     * time somebody edits the menu tree, which is a deploy for what is plainly
     * configuration.
     *
     * So the mapping table stops being the whole membership list and becomes
     * what it is good at: a curation overlay. A row still decides placement and
     * order, and still lets one module borrow another's screens - 22 of the 33
     * menus on the Fees bar and 5 of the 7 on Teach/Learn's sit under a
     * different level-2 parent, which is deliberate and stays exactly as it is.
     * Everything the tree says belongs to the module, and the overlay is silent
     * about, now shows up on its own, categorised by the same heuristic the
     * seeding migration used.
     *
     * A row with `status = 0` is how a menu is kept out of a bar: this skips
     * any menu the module has a row for whatever that row's status says, so
     * hiding one stays a row rather than a special case here.
     *
     * The same rights and tenant filters as the configured items apply, because
     * these are menus like any other and nothing here may widen what a user is
     * allowed to see.
     *
     * @param  list<int>  $permittedMenuIds
     * @param  list<string>  $categoryKeys
     * @return array<string,list<array{id:int,label:string,link:string}>>
     */
    private function autoDiscoveredItems(
        string $moduleName,
        int $level2MenuId,
        string $subInstituteId,
        array $permittedMenuIds,
        array $categoryKeys
    ): array {
        if ($level2MenuId <= 0 || $permittedMenuIds === [] || $categoryKeys === []) {
            return [];
        }

        $configuredMenuIds = DB::table('fees_menu_category_items')
            ->where('module_name', $moduleName)
            ->pluck('menu_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rows = DB::table('tblmenumaster as m')
            ->where('m.parent_menu_id', $level2MenuId)
            ->where('m.level', 3)
            ->where('m.status', 1)
            ->whereIn('m.id', $permittedMenuIds)
            ->whereNotIn('m.id', $configuredMenuIds === [] ? [0] : $configuredMenuIds)
            ->whereRaw('FIND_IN_SET(?, m.sub_institute_id)', [$subInstituteId])
            ->orderBy('m.sort_order')
            ->orderBy('m.id')
            ->get(['m.id', 'm.name', 'm.link', 'm.menu_type']);

        $discovered = [];

        foreach ($rows as $row) {
            $categoryKey = $this->categorize((string) $row->name, $row->menu_type);

            // Every module is seeded every category, but a bar someone has
            // since trimmed may not have the one the heuristic picked. Falling
            // back to Operations keeps the menu reachable; dropping it only
            // when that is missing too keeps this from inventing a category.
            if (! in_array($categoryKey, $categoryKeys, true)) {
                $categoryKey = self::DEFAULT_CATEGORY_KEY;
            }

            if (! in_array($categoryKey, $categoryKeys, true)) {
                continue;
            }

            $discovered[$categoryKey][] = [
                'id' => (int) $row->id,
                'label' => (string) $row->name,
                'link' => (string) $row->link,
            ];
        }

        return $discovered;
    }

    /**
     * Which category a menu belongs to, from the only two signals
     * tblmenumaster carries about it: its name and its menu_type.
     *
     * Deliberately the same rules, in the same order, as the categorize() in
     * 2026_09_17_100001_seed_all_module_menu_categories.php, so a menu that was
     * seeded and a menu that is discovered land in the same place. That
     * migration is history and is not edited; this is where the rules live now,
     * and a placement either of them gets wrong is corrected by a row in
     * `fees_menu_category_items`, which always wins.
     *
     * menu_type='MASTER' is the one authoritative signal — set by the menu
     * tree itself rather than inferred from wording — so it wins outright.
     */
    private function categorize(string $name, ?string $menuType): string
    {
        if (strtoupper(trim((string) $menuType)) === 'MASTER') {
            return 'master-setup';
        }

        $haystack = strtolower($name);

        $rules = [
            'reports' => ['report', 'analysis', 'analytics'],
            'intelligence' => ['dashboard', 'prediction', 'intelligence'],
            'master-setup' => ['setting', 'master', 'setup', 'mapping'],
            'communication' => ['sms', 'email', 'circular', 'notice', 'communication'],
        ];

        foreach ($rules as $categoryKey => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return $categoryKey;
                }
            }
        }

        return self::DEFAULT_CATEGORY_KEY;
    }

    /**
     * The menu ids this user holds a rights row for, via their profile
     * (tblgroupwise_rights) or directly (tblindividual_rights).
     *
     * This mirrors the rights join MenuRightsController performs, kept local so
     * that controller stays untouched. Students resolve through tblstudent,
     * every other profile through tbluser, exactly as it does.
     *
     * @return list<int>
     */
    private function permittedMenuIds(string $subInstituteId, string $userId, string $userProfileName): array
    {
        $isStudent = strtolower(trim($userProfileName)) === 'student';
        $userTable = $isStudent ? 'tblstudent' : 'tbluser';

        return DB::table($userTable.' as u')
            ->leftJoin('tblindividual_rights as i', function ($join) {
                $join->on('u.id', '=', 'i.user_id')
                    ->on('u.sub_institute_id', '=', 'i.sub_institute_id');
            })
            ->leftJoin('tblgroupwise_rights as g', function ($join) {
                $join->on('u.user_profile_id', '=', 'g.profile_id')
                    ->on('u.sub_institute_id', '=', 'g.sub_institute_id');
            })
            ->join('tblmenumaster as m', function ($join) {
                $join->on(function ($on) {
                    $on->on('i.menu_id', '=', 'm.id')->orOn('g.menu_id', '=', 'm.id');
                });
            })
            ->where('u.id', $userId)
            ->where('u.sub_institute_id', $subInstituteId)
            ->distinct()
            ->pluck('m.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
