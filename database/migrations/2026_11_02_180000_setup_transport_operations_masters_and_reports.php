<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transport module tabs (`/modules/transport/*`).
 *
 *  - Operations: Student Transport Mapping (menu 76) moves here from Master Setup.
 *  - Master Setup: lists the old ERP "Transportation Setup" masters (parent menu 48):
 *      49 Add Driver/Conductor, 50 Add Vehicle, 51 Add Route, 52 Add Stop,
 *      127 Map Route-Bus, 128 Map Route-Stop, 381 Add Transport Rate, 387 Add Shift.
 *    Rights are not changed: visibility follows the existing tblgroupwise_rights rows, which the
 *    old ERP already uses for these same menu ids.
 *  - Reports: adds Van Wise Report (129) and Van Summery Report (401). They stay in the Student
 *    reports too, so nothing is taken away from there.
 *  - Schedular: already pinned to the `transport` Platform Scheduler module.
 */
return new class extends Migration
{
    private const MODULE = 'transport';
    private const MAPPING_MENU_ID = 76;
    private const MASTER_MENU_IDS = [49, 50, 51, 52, 127, 128, 381, 387];
    private const REPORT_MENU_IDS = [129, 401];

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        $now = now();

        // Operations: move the mapping screen out of Master Setup.
        $this->place('operations', self::MAPPING_MENU_ID, 1, $now);
        DB::table('fees_menu_category_items')
            ->where('module_name', self::MODULE)->where('category_key', 'master-setup')
            ->where('menu_id', self::MAPPING_MENU_ID)->delete();

        foreach (self::MASTER_MENU_IDS as $i => $menuId) {
            $this->place('master-setup', $menuId, $i + 1, $now);
        }

        foreach (self::REPORT_MENU_IDS as $i => $menuId) {
            $this->place('reports', $menuId, $i + 1, $now);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        DB::table('fees_menu_category_items')
            ->where('module_name', self::MODULE)->where('category_key', 'reports')
            ->whereIn('menu_id', self::REPORT_MENU_IDS)->delete();

        DB::table('fees_menu_category_items')
            ->where('module_name', self::MODULE)->where('category_key', 'master-setup')
            ->whereIn('menu_id', self::MASTER_MENU_IDS)->delete();

        $this->place('master-setup', self::MAPPING_MENU_ID, 1, now());
        DB::table('fees_menu_category_items')
            ->where('module_name', self::MODULE)->where('category_key', 'operations')
            ->where('menu_id', self::MAPPING_MENU_ID)->delete();
    }

    private function place(string $category, int $menuId, int $sortOrder, $now): void
    {
        $existing = DB::table('fees_menu_category_items')
            ->where('module_name', self::MODULE)->where('category_key', $category)
            ->where('menu_id', $menuId)->first();

        if ($existing) {
            DB::table('fees_menu_category_items')->where('id', $existing->id)
                ->update(['sort_order' => $sortOrder, 'status' => 1, 'updated_at' => $now]);

            return;
        }

        DB::table('fees_menu_category_items')->insert([
            'module_name' => self::MODULE,
            'category_key' => $category,
            'menu_id' => $menuId,
            'sort_order' => $sortOrder,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
