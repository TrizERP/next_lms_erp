<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Capture Student Photo" is reference data (the photos face matching compares against), so it
 * belongs under Attendance -> Master Setup rather than Operations. "Capture Attendance" stays
 * on Operations.
 *
 * Only the category of the existing fees_menu_category_items row changes; the menu (500),
 * its rights and its page are untouched. If the row was never added (the Operations
 * migration not run), it is created directly in Master Setup. Idempotent.
 *
 *   php artisan migrate --path=database/migrations/2026_10_09_140000_move_capture_student_photo_to_attendance_master_setup.php
 */
return new class extends Migration
{
    private const MODULE = 'attendance';
    private const FROM = 'operations';
    private const TO = 'master-setup';
    private const LINK = 'student_face_attendance.index';
    private const NAME = 'Capture Student Photo';

    public function up(): void
    {
        $menuId = $this->menuId();
        if (! $menuId || ! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        $hasTarget = DB::table('fees_menu_categories')
            ->where('module_name', self::MODULE)->where('category_key', self::TO)->exists();
        if (! $hasTarget) {
            return;
        }

        $items = DB::table('fees_menu_category_items')->where('module_name', self::MODULE)->where('menu_id', $menuId);

        if ((clone $items)->where('category_key', self::TO)->exists()) {
            (clone $items)->where('category_key', self::FROM)->delete();

            return;
        }

        $next = (int) DB::table('fees_menu_category_items')
            ->where('module_name', self::MODULE)->where('category_key', self::TO)->max('sort_order');

        if ((clone $items)->where('category_key', self::FROM)->exists()) {
            (clone $items)->where('category_key', self::FROM)
                ->update(['category_key' => self::TO, 'sort_order' => $next + 1, 'updated_at' => now()]);

            return;
        }

        DB::table('fees_menu_category_items')->insert([
            'module_name' => self::MODULE, 'category_key' => self::TO, 'menu_id' => $menuId,
            'sort_order' => $next + 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $menuId = $this->menuId();
        if (! $menuId || ! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        DB::table('fees_menu_category_items')
            ->where('module_name', self::MODULE)->where('menu_id', $menuId)->where('category_key', self::TO)
            ->update(['category_key' => self::FROM, 'updated_at' => now()]);
    }

    private function menuId(): ?int
    {
        $id = DB::table('tblmenumaster')->where('level', 3)->where('link', self::LINK)->where('name', self::NAME)->value('id');

        return $id ? (int) $id : null;
    }
};
