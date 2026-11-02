<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the Timetable module's "Reports" tab (`/modules/timetable/reports`) list
 * the timetable and proxy report screens from Institute Report (level-2 menu 299).
 *
 * Menus added:
 *   - 23:  View Classwise Timetable (classwisetimetable.index) -> /front_desk/classwisetimetable
 *   - 24:  View Facultywise Timetable (facultywisetimetable.index) -> /front_desk/facultywisetimetable
 *   - 104: Proxy Report (/proxy_report) -> /proxy_report
 *   - 298: Class Teacher Report (/classteacherReport) -> /classteacherReport
 *   - 300: Todays Proxy Report (/todays_proxy_report) -> /todays_proxy_report
 */
return new class extends Migration
{
    private const TARGET_MODULE = 'timetable';
    private const CATEGORY_KEY = 'reports';

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        // 1. Ensure fees_menu_categories has the reports category for timetable
        if (Schema::hasTable('fees_menu_categories')) {
            $cat = DB::table('fees_menu_categories')
                ->where('module_name', self::TARGET_MODULE)
                ->where('category_key', self::CATEGORY_KEY)
                ->first();

            if ($cat === null) {
                $level2MenuId = DB::table('fees_menu_categories')
                    ->where('module_name', self::TARGET_MODULE)
                    ->whereNotNull('level2_menu_id')
                    ->value('level2_menu_id') ?: 434;

                DB::table('fees_menu_categories')->insert([
                    'module_name' => self::TARGET_MODULE,
                    'level2_menu_id' => $level2MenuId,
                    'category_key' => self::CATEGORY_KEY,
                    'label' => 'Reports',
                    'description' => 'Timetable and proxy substitution reporting.',
                    'route' => '/modules/'.self::TARGET_MODULE.'/'.self::CATEGORY_KEY,
                    'sort_order' => 5,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. Insert or update the 5 timetable report items
        $items = [
            ['menu_id' => 23,  'sort_order' => 1],
            ['menu_id' => 24,  'sort_order' => 2],
            ['menu_id' => 104, 'sort_order' => 3],
            ['menu_id' => 298, 'sort_order' => 4],
            ['menu_id' => 300, 'sort_order' => 5],
        ];

        $now = now();
        foreach ($items as $item) {
            $existing = DB::table('fees_menu_category_items')
                ->where('module_name', self::TARGET_MODULE)
                ->where('category_key', self::CATEGORY_KEY)
                ->where('menu_id', $item['menu_id'])
                ->first();

            if ($existing) {
                DB::table('fees_menu_category_items')
                    ->where('id', $existing->id)
                    ->update([
                        'sort_order' => $item['sort_order'],
                        'status' => 1,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('fees_menu_category_items')->insert([
                    'module_name' => self::TARGET_MODULE,
                    'category_key' => self::CATEGORY_KEY,
                    'menu_id' => $item['menu_id'],
                    'sort_order' => $item['sort_order'],
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 3. Update platform_module_key for schedular and workflow in fees_menu_categories
        if (Schema::hasTable('fees_menu_categories')) {
            DB::table('fees_menu_categories')
                ->where('module_name', self::TARGET_MODULE)
                ->whereIn('category_key', ['schedular', 'workflow'])
                ->update([
                    'platform_module_key' => 'timetable',
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
            ->where('module_name', self::TARGET_MODULE)
            ->where('category_key', self::CATEGORY_KEY)
            ->whereIn('menu_id', [23, 24, 104, 298, 300])
            ->delete();
    }
};

