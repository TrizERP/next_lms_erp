<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the Student module's "Reports" tab (`/modules/student/reports`) list
 * the same report screens that the "Student Report" module already has.
 *
 * WHY IT WAS EMPTY
 * The report menus (Student Report, Strength, Agewise, Certificate, Health,
 * Homework, Attendance ...) hang under the level-2 menu "Student Report"
 * (id 91), which is its own module `student-report`. The Student module is
 * the level-2 menu "Student" (id 259); it has a `reports` category row but no
 * menu under 259 is report-like and `fees_menu_category_items` held nothing
 * for (student, reports), so the tab rendered "Nothing here yet".
 *
 * The fix is the curation overlay that AbstractMenuCategoryApiController
 * already reads: explicit rows in `fees_menu_category_items` let one module's
 * bar borrow another's screens. The rows are copied from `student-report`'s
 * own reports category, so both bars show the same list in the same order and
 * the rights/tenant filters of the feed still decide who sees what.
 *
 * Idempotent; `down()` removes only the rows this adds.
 */
return new class extends Migration
{
    private const TARGET_MODULE = 'student';
    private const SOURCE_MODULE = 'student-report';
    private const CATEGORY_KEY = 'reports';

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        $source = DB::table('fees_menu_category_items')
            ->where('module_name', self::SOURCE_MODULE)
            ->where('category_key', self::CATEGORY_KEY)
            ->where('status', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['menu_id', 'sort_order']);

        foreach ($source as $row) {
            $exists = DB::table('fees_menu_category_items')
                ->where('module_name', self::TARGET_MODULE)
                ->where('menu_id', $row->menu_id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('fees_menu_category_items')->insert([
                'module_name' => self::TARGET_MODULE,
                'category_key' => self::CATEGORY_KEY,
                'menu_id' => $row->menu_id,
                'sort_order' => $row->sort_order,
                'status' => 1,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        $menuIds = DB::table('fees_menu_category_items')
            ->where('module_name', self::SOURCE_MODULE)
            ->where('category_key', self::CATEGORY_KEY)
            ->pluck('menu_id');

        DB::table('fees_menu_category_items')
            ->where('module_name', self::TARGET_MODULE)
            ->where('category_key', self::CATEGORY_KEY)
            ->whereIn('menu_id', $menuIds)
            ->delete();
    }
};
