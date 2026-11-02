<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PTM module tabs.
 *
 *  - Reports: moves "PTM Report" (menu 458, /admin-services/ptm-report) from the shared
 *    Other Reports bar into the PTM module's own Reports tab.
 *  - Schedular: pins the PTM Schedular tab to the `ptm` Platform Scheduler module.
 *
 * The Operations tab is left as it is.
 */
return new class extends Migration
{
    private const MODULE = 'ptm';
    private const SOURCE_MODULE = 'other-reports';
    private const MENU_ID = 458;
    private const PLATFORM_MODULE_KEY = 'ptm';

    public function up(): void
    {
        $now = now();

        if (Schema::hasTable('fees_menu_category_items')) {
            $target = DB::table('fees_menu_category_items')
                ->where('module_name', self::MODULE)->where('category_key', 'reports')
                ->where('menu_id', self::MENU_ID)->first();

            if ($target) {
                DB::table('fees_menu_category_items')->where('id', $target->id)
                    ->update(['sort_order' => 1, 'status' => 1, 'updated_at' => $now]);
                DB::table('fees_menu_category_items')
                    ->where('module_name', self::SOURCE_MODULE)->where('category_key', 'reports')
                    ->where('menu_id', self::MENU_ID)->delete();
            } else {
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
        }

        if (Schema::hasTable('fees_menu_categories') && Schema::hasColumn('fees_menu_categories', 'platform_module_key')) {
            DB::table('fees_menu_categories')
                ->where('module_name', self::MODULE)->where('category_key', 'schedular')
                ->update(['platform_module_key' => self::PLATFORM_MODULE_KEY, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fees_menu_category_items')) {
            DB::table('fees_menu_category_items')
                ->where('module_name', self::MODULE)->where('category_key', 'reports')
                ->where('menu_id', self::MENU_ID)
                ->update(['module_name' => self::SOURCE_MODULE, 'updated_at' => now()]);
        }

        if (Schema::hasTable('fees_menu_categories') && Schema::hasColumn('fees_menu_categories', 'platform_module_key')) {
            DB::table('fees_menu_categories')
                ->where('module_name', self::MODULE)->where('category_key', 'schedular')
                ->update(['platform_module_key' => null, 'updated_at' => now()]);
        }
    }
};
