<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves "Front Desk Report" (menu 157, /admin-services/front-desk-report) from the shared
 * Other Reports bar into the Front Desk module's own Reports tab.
 *
 * The Front Desk Schedular tab is already pinned to the `front_desk` Platform Scheduler module.
 * "Complaint Report" (menu 149) belongs to the Complaint module and is not touched.
 */
return new class extends Migration
{
    private const MODULE = 'front-desk';
    private const SOURCE_MODULE = 'other-reports';
    private const MENU_ID = 157;

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        $now = now();

        $target = DB::table('fees_menu_category_items')
            ->where('module_name', self::MODULE)->where('category_key', 'reports')
            ->where('menu_id', self::MENU_ID)->first();

        if ($target) {
            DB::table('fees_menu_category_items')->where('id', $target->id)
                ->update(['sort_order' => 1, 'status' => 1, 'updated_at' => $now]);
            DB::table('fees_menu_category_items')
                ->where('module_name', self::SOURCE_MODULE)->where('category_key', 'reports')
                ->where('menu_id', self::MENU_ID)->delete();

            return;
        }

        $moved = DB::table('fees_menu_category_items')
            ->where('module_name', self::SOURCE_MODULE)->where('category_key', 'reports')
            ->where('menu_id', self::MENU_ID)
            ->update(['module_name' => self::MODULE, 'sort_order' => 1, 'status' => 1, 'updated_at' => $now]);

        if (! $moved) {
            DB::table('fees_menu_category_items')->insert([
                'module_name' => self::MODULE,
                'category_key' => 'reports',
                'menu_id' => self::MENU_ID,
                'sort_order' => 1,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        DB::table('fees_menu_category_items')
            ->where('module_name', self::MODULE)->where('category_key', 'reports')
            ->where('menu_id', self::MENU_ID)
            ->update(['module_name' => self::SOURCE_MODULE, 'updated_at' => now()]);
    }
};
