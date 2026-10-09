<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Puts "Capture Attendance" on Attendance -> Operations. ("Capture Student Photo" was first added here too and now lives in
 * Master Setup - see 2026_10_09_140000.)
 *
 * Both screens already exist as tblmenumaster rows (under the "(AI) Artificial
 * Intelligence" menu, links class_face_attendance.index / student_face_attendance.index)
 * and have Next.js pages. This only adds two rows to fees_menu_category_items, which
 * REFERENCE those menus - no tblmenumaster row is created, moved or changed, so there is
 * no duplicate menu, and visibility still follows the menu's own status, tenant list and
 * the caller's rights (admins/teachers/students see exactly what they could before).
 *
 * Items are matched on (module, category, menu) so re-running never duplicates, and are
 * placed after the existing Operations menus. Run on its own - never a bare
 * `php artisan migrate` on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_10_09_120000_add_capture_attendance_and_photo_to_attendance_operations.php
 */
return new class extends Migration
{
    private const MODULE = 'attendance';
    private const CATEGORY = 'operations';

    /** tblmenumaster.link => label, in tab order. */
    private const MENUS = [
        'class_face_attendance.index' => 'Capture Attendance',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_category_items') || ! Schema::hasTable('fees_menu_categories')) {
            return;
        }

        // Only if Attendance actually has an Operations category here.
        $hasCategory = DB::table('fees_menu_categories')
            ->where('module_name', self::MODULE)->where('category_key', self::CATEGORY)->exists();
        if (! $hasCategory) {
            return;
        }

        $next = (int) DB::table('fees_menu_category_items')
            ->where('module_name', self::MODULE)->where('category_key', self::CATEGORY)->max('sort_order');

        foreach (self::MENUS as $link => $name) {
            $menuId = DB::table('tblmenumaster')->where('level', 3)->where('link', $link)->where('name', $name)->value('id');
            if (! $menuId) {
                continue;
            }

            $exists = DB::table('fees_menu_category_items')
                ->where('module_name', self::MODULE)->where('category_key', self::CATEGORY)->where('menu_id', $menuId)->exists();
            if ($exists) {
                continue;
            }

            DB::table('fees_menu_category_items')->insert([
                'module_name' => self::MODULE,
                'category_key' => self::CATEGORY,
                'menu_id' => $menuId,
                'sort_order' => ++$next,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        $menuIds = DB::table('tblmenumaster')->where('level', 3)->whereIn('link', array_keys(self::MENUS))->pluck('id');

        DB::table('fees_menu_category_items')
            ->where('module_name', self::MODULE)->where('category_key', self::CATEGORY)
            ->whereIn('menu_id', $menuIds)->delete();
    }
};
