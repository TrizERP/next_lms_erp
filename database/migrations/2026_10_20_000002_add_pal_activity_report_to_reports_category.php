<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes "Activity Report" and "Class Activity Report" show up as cards on
 * the PAL "Reports" tab (`/modules/new-pal/reports`), alongside the existing
 * "Coverage and attainment" card.
 *
 * WHY THIS IS A SEPARATE MIGRATION FROM 2026_10_20_000001
 * That migration only writes `tblmenumaster`/`tblgroupwise_rights` — correct
 * for the sidebar (useMenuRights/buildMenuTree walk the tree at any depth),
 * but the Reports tab's card grid is a DIFFERENT feed:
 * AbstractMenuCategoryApiController::autoDiscoveredItems() only picks up
 * `tblmenumaster` rows that are LEVEL 3 children of the module's own level-2
 * row (529/531, "PAL"). The two new rows are level 4 (children of the
 * level-3 "Reports" row, `new_pal.reports`), so they are structurally
 * invisible to that auto-discovery query regardless of rights. The fix is
 * the OTHER source that controller reads: an explicit row in
 * `fees_menu_category_items`, keyed by module_name + category_key, which the
 * controller always unions in ahead of auto-discovery.
 *
 * module_name='new-pal' and category_key='reports' are read directly off the
 * live `fees_menu_categories` row for PAL's Reports tab (id 539) — not
 * guessed, since `new_pal` (underscore, the tblmenumaster link prefix) and
 * `new-pal` (hyphen, this table's module_name) are two different spellings
 * in this codebase and only one of them matches here.
 *
 * Idempotent, and narrow: only these two (module_name, category_key, menu_id)
 * triples are touched.
 */
return new class extends Migration
{
    private const MODULE_NAME = 'new-pal';
    private const CATEGORY_KEY = 'reports';

    /** link => sort_order, matching the order already set in tblmenumaster. */
    private const ITEMS = [
        'new_pal.activity_report' => 1,
        'new_pal.class_activity_report' => 2,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_category_items') || ! Schema::hasTable('tblmenumaster')) {
            return;
        }

        foreach (self::ITEMS as $link => $sortOrder) {
            $menu = DB::table('tblmenumaster')->where('link', $link)->first(['id']);
            if ($menu === null) {
                // Its own menu row doesn't exist yet (2026_10_20_000001 hasn't
                // run) — nothing to attach to, so skip rather than guess.
                continue;
            }

            $exists = DB::table('fees_menu_category_items')
                ->where('module_name', self::MODULE_NAME)
                ->where('category_key', self::CATEGORY_KEY)
                ->where('menu_id', $menu->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('fees_menu_category_items')->insert([
                'module_name' => self::MODULE_NAME,
                'category_key' => self::CATEGORY_KEY,
                'menu_id' => $menu->id,
                'sort_order' => $sortOrder,
                'status' => 1,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_category_items') || ! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $menuIds = DB::table('tblmenumaster')->whereIn('link', array_keys(self::ITEMS))->pluck('id');

        if ($menuIds->isEmpty()) {
            return;
        }

        DB::table('fees_menu_category_items')
            ->where('module_name', self::MODULE_NAME)
            ->where('category_key', self::CATEGORY_KEY)
            ->whereIn('menu_id', $menuIds)
            ->delete();
    }
};
