<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fills the Student module's "Communication" tab (`/modules/student/communication`),
 * which rendered "Nothing here yet".
 *
 * WHY IT WAS EMPTY
 * The Student level-2 menu (id 259) has no communication screens of its own, and
 * `fees_menu_category_items` held nothing for (student, communication). The SMS and
 * email screens live under the Communication module (`easy_com`).
 *
 * The fix is the curation overlay AbstractMenuCategoryApiController already reads:
 * explicit `fees_menu_category_items` rows let one module's bar borrow another's
 * screens. Menus are resolved by their `link` rather than a hardcoded id, the same
 * way 2026_10_20_000002 does, and the controller's rights/tenant filters still
 * decide who sees what.
 *
 * Idempotent; `down()` removes only the rows this adds.
 */
return new class extends Migration
{
    private const MODULE_NAME = 'student';
    private const CATEGORY_KEY = 'communication';

    /** link => sort_order */
    private const ITEMS = [
        '/easy_com/send_sms_report' => 1,
        '/easy_com/email_report' => 2,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_category_items') || ! Schema::hasTable('tblmenumaster')) {
            return;
        }

        foreach (self::ITEMS as $link => $sortOrder) {
            $menu = DB::table('tblmenumaster')->where('link', $link)->where('status', 1)->first(['id']);
            if ($menu === null) {
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
