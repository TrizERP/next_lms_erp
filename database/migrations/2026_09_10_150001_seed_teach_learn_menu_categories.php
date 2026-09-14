<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the Teach/Learn category bar into the now-shared
 * `fees_menu_categories` / `fees_menu_category_items` tables (module_name =
 * 'teach_learn'), mirroring the ten live Fees categories one for one so the
 * two modules present the same category bar shape.
 *
 * Teach/Learn (tblmenumaster id 269, level 2, under "LMS") currently has only
 * two active level-3 screens — "Course Catalog" and "LMS Global Mapping"; the
 * other two children ("H5P content", "Content Library") are status=0 and are
 * not seeded, exactly as Fees Prediction's disabled parent is skipped by
 * resolveMenuId() ignoring status. Every other category is intentionally
 * seeded with zero items, the same way Fees' Onboarding/Help Guide/SOP
 * categories start empty until real screens exist for them.
 *
 * Idempotent, same as 2026_09_05_110000: categories are matched on
 * (module_name, category_key), items on (module_name, category_key, menu_id).
 */
return new class extends Migration
{
    /** category key => [label, description, sort_order] */
    private const CATEGORIES = [
        'onboarding' => ['Onboarding', 'Set up a new course or academic year for Teach/Learn.', 1],
        'process-builder' => ['Process Builder', 'Design and manage Teach/Learn processes and approval flows.', 2],
        'master-setup' => ['Master Setup', 'Course mapping and other Teach/Learn configuration.', 3],
        'operations' => ['Operations', 'Day-to-day course and content management.', 4],
        'reports' => ['Reports', 'Teach/Learn usage and progress reporting.', 5],
        'intelligence' => ['Intelligence', 'Predictive and analytical views over Teach/Learn data.', 6],
        'help-guide-support' => ['Help Guide/Support', 'Guides and support material for Teach/Learn.', 7],
        'sop-task' => ['SOP / Task', 'Standard operating procedures and Teach/Learn tasks.', 8],
        'communication' => ['Communication', 'Teach/Learn notices and announcements.', 9],
        'ai-stack' => ['AI Stack', 'AI services and automation for Teach/Learn.', 10],
    ];

    /** category key => ordered [parent name, parent level, menu name] */
    private const ITEMS = [
        'master-setup' => [
            ['Teach/Learn', 2, 'LMS Global Mapping'],
        ],
        'operations' => [
            ['Teach/Learn', 2, 'Course Catalog'],
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories') || ! Schema::hasColumn('fees_menu_categories', 'module_name')) {
            return;
        }

        foreach (self::CATEGORIES as $key => [$label, $description, $sortOrder]) {
            $existing = DB::table('fees_menu_categories')
                ->where('module_name', 'teach_learn')
                ->where('category_key', $key)
                ->first();

            if ($existing === null) {
                DB::table('fees_menu_categories')->insert([
                    'module_name' => 'teach_learn',
                    'category_key' => $key,
                    'label' => $label,
                    'description' => $description,
                    'route' => '/teach-learn/'.$key,
                    'sort_order' => $sortOrder,
                    'status' => 1,
                    'created_at' => now(),
                ]);

                continue;
            }

            DB::table('fees_menu_categories')->where('id', $existing->id)->update([
                'sort_order' => $sortOrder,
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('tblmenumaster') || ! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        foreach (self::ITEMS as $categoryKey => $items) {
            $sortOrder = 0;

            foreach ($items as [$parentName, $parentLevel, $menuName]) {
                $menuId = $this->resolveMenuId($parentName, $parentLevel, $menuName);
                if ($menuId === null) {
                    continue;
                }

                $sortOrder++;

                $existing = DB::table('fees_menu_category_items')
                    ->where('module_name', 'teach_learn')
                    ->where('category_key', $categoryKey)
                    ->where('menu_id', $menuId)
                    ->first();

                if ($existing === null) {
                    DB::table('fees_menu_category_items')->insert([
                        'module_name' => 'teach_learn',
                        'category_key' => $categoryKey,
                        'menu_id' => $menuId,
                        'sort_order' => $sortOrder,
                        'status' => 1,
                        'created_at' => now(),
                    ]);

                    continue;
                }

                DB::table('fees_menu_category_items')->where('id', $existing->id)->update([
                    'sort_order' => $sortOrder,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fees_menu_category_items')) {
            DB::table('fees_menu_category_items')->where('module_name', 'teach_learn')->delete();
        }

        if (Schema::hasTable('fees_menu_categories')) {
            DB::table('fees_menu_categories')->where('module_name', 'teach_learn')->delete();
        }
    }

    /**
     * Mirrors FeesMenuCategoryApiController migration's resolveMenuId(): match
     * by (parent name, parent level, menu name) rather than a hardcoded id,
     * since ids are not portable across tenants. The parent is matched without
     * a status filter on purpose — visibility is decided at read time.
     */
    private function resolveMenuId(string $parentName, int $parentLevel, string $menuName): ?int
    {
        $parentId = DB::table('tblmenumaster')
            ->where('name', $parentName)
            ->where('level', $parentLevel)
            ->orderBy('id')
            ->value('id');

        if ($parentId === null) {
            return null;
        }

        $id = DB::table('tblmenumaster')
            ->where('parent_menu_id', $parentId)
            ->where('name', $menuName)
            ->orderBy('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }
};
