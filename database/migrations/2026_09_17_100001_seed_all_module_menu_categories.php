<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rolls the level-3 category bar out to every remaining module.
 *
 * Fees proved the pattern and Teach/Learn repeated it by hand; this seeds the
 * other 62 — every active level-2 menu that has at least one active level-3
 * child, which is the only kind of menu a category bar can group. A level-2
 * menu with no children is a screen, not a module, and is skipped.
 *
 * Membership is assigned by the heuristic in categorize() rather than decided
 * screen by screen. That is deliberate, and the rows it writes are ordinary
 * configuration — editable afterwards without a deploy. The alternative, 314
 * hand-placed menus across 62 modules, would have been stale before it
 * shipped. The heuristic reads the menu's own name and menu_type, the only
 * signals the tree actually carries. Expect to move a handful of rows by hand
 * afterwards; that is the intended workflow, not a defect.
 *
 * Every category is seeded for every module even when it would be empty, so
 * the bar has a stable shape across modules and a screen added later lands in
 * a category that already exists. Fees and Teach/Learn already do this — most
 * of their categories started empty too.
 *
 * Idempotent, matching 2026_09_05_110000 and 2026_09_10_150001: categories are
 * matched on (module_name, category_key), items on (module_name, category_key,
 * menu_id). Re-running never duplicates a row and never overwrites a label,
 * route or membership someone has since corrected by hand.
 */
return new class extends Migration
{
    /** The level-2 menus that already have a bar, by tblmenumaster.id. */
    private const ALREADY_SEEDED = [6, 269];

    /** category key => [label, description format, sort_order] */
    private const CATEGORIES = [
        'onboarding' => ['Onboarding', 'Get started with %s.', 1],
        'process-builder' => ['Process Builder', 'Design and manage %s processes and approval flows.', 2],
        'master-setup' => ['Master Setup', 'Configuration and master data for %s.', 3],
        'operations' => ['Operations', 'Day-to-day %s screens.', 4],
        'reports' => ['Reports', '%s reporting and analysis.', 5],
        'intelligence' => ['Intelligence', 'Predictive and analytical views over %s data.', 6],
        'help-guide-support' => ['Help Guide/Support', 'Guides and support material for %s.', 7],
        'sop-task' => ['SOP / Task', 'Standard operating procedures and %s tasks.', 8],
        'communication' => ['Communication', '%s notices and announcements.', 9],
        'ai-stack' => ['AI Stack', 'AI services and automation for %s.', 10],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories')
            || ! Schema::hasTable('fees_menu_category_items')
            || ! Schema::hasColumn('fees_menu_categories', 'level2_menu_id')) {
            return;
        }

        $now = now();

        foreach ($this->modules() as $module) {
            foreach (self::CATEGORIES as $key => [$label, $descriptionFormat, $sortOrder]) {
                $exists = DB::table('fees_menu_categories')
                    ->where('module_name', $module['slug'])
                    ->where('category_key', $key)
                    ->exists();

                if (! $exists) {
                    DB::table('fees_menu_categories')->insert([
                        'module_name' => $module['slug'],
                        'level2_menu_id' => $module['id'],
                        'category_key' => $key,
                        'label' => $label,
                        'description' => sprintf($descriptionFormat, $module['label']),
                        // One dynamic route serves every module's category
                        // pages — see app/modules/[moduleKey]/[categoryKey].
                        'route' => '/modules/'.$module['slug'].'/'.$key,
                        'sort_order' => $sortOrder,
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $sortByCategory = [];

            foreach ($module['menus'] as $menu) {
                $categoryKey = $this->categorize((string) $menu->name, $menu->menu_type);
                $sortByCategory[$categoryKey] = ($sortByCategory[$categoryKey] ?? 0) + 1;

                $exists = DB::table('fees_menu_category_items')
                    ->where('module_name', $module['slug'])
                    ->where('category_key', $categoryKey)
                    ->where('menu_id', $menu->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('fees_menu_category_items')->insert([
                    'module_name' => $module['slug'],
                    'category_key' => $categoryKey,
                    'menu_id' => $menu->id,
                    'sort_order' => $sortByCategory[$categoryKey],
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            return;
        }

        // Only the modules this migration introduced. 'fees' and 'teach_learn'
        // were seeded by earlier migrations and are left exactly as they are.
        $slugs = array_column($this->modules(), 'slug');

        if ($slugs === []) {
            return;
        }

        DB::table('fees_menu_category_items')->whereIn('module_name', $slugs)->delete();
        DB::table('fees_menu_categories')->whereIn('module_name', $slugs)->delete();
    }

    /**
     * Every module that should get a bar: an active level-2 menu with at least
     * one active level-3 child, minus the two already seeded.
     *
     * @return list<array{id:int,label:string,slug:string,menus:list<object>}>
     */
    private function modules(): array
    {
        $level2 = DB::table('tblmenumaster')
            ->where('level', 2)
            ->where('status', 1)
            ->orderBy('id')
            ->get(['id', 'name']);

        $candidates = [];

        foreach ($level2 as $menu) {
            if (in_array((int) $menu->id, self::ALREADY_SEEDED, true)) {
                continue;
            }

            $children = DB::table('tblmenumaster')
                ->where('parent_menu_id', $menu->id)
                ->where('level', 3)
                ->where('status', 1)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'menu_type']);

            if ($children->isEmpty()) {
                continue;
            }

            $slug = $this->slug((string) $menu->name);

            if ($slug === '') {
                continue;
            }

            $candidates[] = [
                'id' => (int) $menu->id,
                'label' => (string) $menu->name,
                'slug' => $slug,
                'menus' => $children->all(),
            ];
        }

        return $this->disambiguate($candidates);
    }

    /**
     * A menu name is not unique, so a slug derived from one is not either:
     * "Task Management" exists twice (ids 253 and 551). Both members of a
     * collision take the id suffix rather than first-come keeping the bare
     * slug, so which module owns which name never depends on insertion order.
     *
     * @param  list<array{id:int,label:string,slug:string,menus:list<object>}>  $modules
     * @return list<array{id:int,label:string,slug:string,menus:list<object>}>
     */
    private function disambiguate(array $modules): array
    {
        $counts = array_count_values(array_column($modules, 'slug'));

        foreach ($modules as $index => $module) {
            if (($counts[$module['slug']] ?? 0) > 1) {
                $modules[$index]['slug'] = $module['slug'].'-'.$module['id'];
            }
        }

        return $modules;
    }

    private function slug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    /**
     * Which category an existing menu belongs to, from the only two signals
     * tblmenumaster carries about it: its name and its menu_type.
     *
     * Ordered most specific first. menu_type='MASTER' is the one authoritative
     * signal — it is set by the menu tree itself rather than inferred from
     * wording — so it wins outright. Everything after it is a name match, and
     * 'operations' is the fallback because a screen that announces nothing
     * about itself is a day-to-day screen.
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

        return 'operations';
    }
};
