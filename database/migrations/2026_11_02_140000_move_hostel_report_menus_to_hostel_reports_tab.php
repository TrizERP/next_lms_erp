<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the report screens of the Hostel Report module (`/modules/hostel-report/reports`)
 * into the Hostel module's "Reports" tab (`/modules/hostel/reports`).
 *
 * Menus moved (fees_menu_category_items, module hostel-report -> hostel):
 *   118 Visitor Report, 119 Hostel Report, 120 Available Room Report
 */
return new class extends Migration
{
    private const SOURCE_MODULE = 'hostel-report';
    private const TARGET_MODULE = 'hostel';
    private const CATEGORY_KEY = 'reports';
    private const MENU_IDS = [118, 119, 120];

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        $now = now();

        foreach (self::MENU_IDS as $i => $menuId) {
            $sortOrder = $i + 1;

            $target = DB::table('fees_menu_category_items')
                ->where('module_name', self::TARGET_MODULE)
                ->where('category_key', self::CATEGORY_KEY)
                ->where('menu_id', $menuId)
                ->first();

            if ($target) {
                DB::table('fees_menu_category_items')
                    ->where('id', $target->id)
                    ->update(['sort_order' => $sortOrder, 'status' => 1, 'updated_at' => $now]);

                DB::table('fees_menu_category_items')
                    ->where('module_name', self::SOURCE_MODULE)
                    ->where('category_key', self::CATEGORY_KEY)
                    ->where('menu_id', $menuId)
                    ->delete();

                continue;
            }

            $moved = DB::table('fees_menu_category_items')
                ->where('module_name', self::SOURCE_MODULE)
                ->where('category_key', self::CATEGORY_KEY)
                ->where('menu_id', $menuId)
                ->update([
                    'module_name' => self::TARGET_MODULE,
                    'sort_order' => $sortOrder,
                    'status' => 1,
                    'updated_at' => $now,
                ]);

            if (! $moved) {
                DB::table('fees_menu_category_items')->insert([
                    'module_name' => self::TARGET_MODULE,
                    'category_key' => self::CATEGORY_KEY,
                    'menu_id' => $menuId,
                    'sort_order' => $sortOrder,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        DB::table('fees_menu_category_items')
            ->where('module_name', self::TARGET_MODULE)
            ->where('category_key', self::CATEGORY_KEY)
            ->whereIn('menu_id', self::MENU_IDS)
            ->update(['module_name' => self::SOURCE_MODULE, 'updated_at' => now()]);
    }
};
