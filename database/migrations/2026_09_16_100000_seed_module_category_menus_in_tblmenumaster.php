<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Publishes the Fees and Teach/Learn categories into the real menu tree.
 *
 * `fees_menu_categories` is the only place the category layer (Onboarding,
 * Master Setup, Operations, Reports, AI Stack, Audit Trail, ...) currently
 * exists. Everything that reads the menu tree -- profile-wise rights
 * (`tblprofilewise_menu`), the permission middleware, the sidebar API -- reads
 * `tblmenumaster`, so a category page has no menu row to hang rights on and is
 * invisible to that machinery.
 *
 * This migration mirrors each category into `tblmenumaster` as a level-2 menu
 * sitting alongside its module: the Fees categories under Institute ERP (the
 * parent of `Fees`), the Teach/Learn categories under LMS + PAL (the parent of
 * `Teach/Learn`). Names are module-qualified ("Fees Reports", "Teach/Learn
 * Reports") because at level 2 the two modules' categories share one grid and a
 * bare "Reports" tile would say nothing about which module it belongs to --
 * the same convention the tree already uses for "Fees Report" and "LMS Report".
 *
 * The rows are derived from `fees_menu_categories` rather than hardcoded, and
 * are matched on (parent_menu_id, link), so re-running this refreshes labels,
 * ordering and status instead of duplicating.
 */
return new class extends Migration
{
    /** Source module key => the level-2 menu it belongs to, and how to label it. */
    private const MODULES = [
        'fees' => ['menu_id' => 6, 'menu_name' => 'Fees', 'prefix' => 'Fees'],
        'teach_learn' => ['menu_id' => 269, 'menu_name' => 'Teach/Learn', 'prefix' => 'Teach/Learn'],
    ];

    private const ICONS = [
        'onboarding' => 'mdi mdi-rocket-launch-outline',
        'process-builder' => 'mdi mdi-sitemap-outline',
        'master-setup' => 'mdi mdi-cog-outline',
        'operations' => 'mdi mdi-clipboard-check-outline',
        'reports' => 'mdi mdi-chart-bar',
        'intelligence' => 'mdi mdi-chart-timeline-variant',
        'help-guide-support' => 'mdi mdi-help-circle-outline',
        'sop-task' => 'mdi mdi-format-list-checks',
        'communication' => 'mdi mdi-bell-outline',
        'ai-stack' => 'mdi mdi-brain',
        'workflow' => 'mdi mdi-source-branch',
        'schedular' => 'mdi mdi-clock-outline',
        'audit-trail' => 'mdi mdi-history',
    ];

    /**
     * Category menus sort after everything already in the grid, in category
     * order, so no existing tile moves.
     */
    private const SORT_BASE = 700;

    public function up(): void
    {
        $now = now();

        foreach (self::MODULES as $moduleName => $module) {
            $moduleMenu = DB::table('tblmenumaster')
                ->where('id', $module['menu_id'])
                ->where('name', $module['menu_name'])
                ->first();

            if (!$moduleMenu) {
                // The module menu is the anchor for the parent, tenant list and
                // client list. Without it there is nothing safe to attach to.
                continue;
            }

            $categories = DB::table('fees_menu_categories')
                ->where('module_name', $moduleName)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $position = 0;

            foreach ($categories as $category) {
                $position++;

                $link = trim((string) $category->route);
                if ($link === '') {
                    // Nothing to navigate to; a menu row would be a dead tile.
                    continue;
                }

                $row = [
                    'name' => $module['prefix'] . ' ' . $category->label,
                    'menu_title' => $module['menu_name'],
                    'menu_sortorder' => null,
                    'description' => (string) ($category->description ?? ''),
                    'parent_menu_id' => (int) $moduleMenu->parent_menu_id,
                    'level' => 2,
                    'status' => (int) $category->status,
                    'sort_order' => self::SORT_BASE + $position,
                    'link' => $link,
                    'icon' => self::ICONS[$category->category_key] ?? 'mdi mdi-folder-outline',
                    'sub_institute_id' => $moduleMenu->sub_institute_id,
                    'client_id' => $moduleMenu->client_id,
                    'menu_type' => 'ENTRY',
                    'database_table' => null,
                    'site_map_name' => null,
                    'menu_path' => $category->label,
                ];

                $existing = DB::table('tblmenumaster')
                    ->where('parent_menu_id', (int) $moduleMenu->parent_menu_id)
                    ->where('link', $link)
                    ->where('level', 2)
                    ->first();

                if ($existing) {
                    DB::table('tblmenumaster')
                        ->where('id', $existing->id)
                        ->update($row + ['updated_at' => $now]);

                    continue;
                }

                DB::table('tblmenumaster')->insert($row + ['created_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::MODULES as $moduleName => $module) {
            $moduleMenu = DB::table('tblmenumaster')
                ->where('id', $module['menu_id'])
                ->where('name', $module['menu_name'])
                ->first();

            if (!$moduleMenu) {
                continue;
            }

            $links = DB::table('fees_menu_categories')
                ->where('module_name', $moduleName)
                ->pluck('route')
                ->filter(fn ($route) => trim((string) $route) !== '')
                ->map(fn ($route) => trim((string) $route))
                ->all();

            if (!$links) {
                continue;
            }

            // Scoped by the module-qualified name so a pre-existing menu that
            // happens to share a route is never removed.
            DB::table('tblmenumaster')
                ->where('parent_menu_id', (int) $moduleMenu->parent_menu_id)
                ->where('level', 2)
                ->where('menu_title', $module['menu_name'])
                ->whereIn('link', $links)
                ->delete();
        }
    }
};
