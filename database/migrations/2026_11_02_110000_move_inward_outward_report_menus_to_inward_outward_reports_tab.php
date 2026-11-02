<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the report screens of the Inward Outward Report module
 * (`/modules/inward-outward-report/reports`) into the Inward Outward module's
 * "Reports" tab (`/modules/inward-outward/reports`).
 *
 * Menus moved (fees_menu_category_items, module inward-outward-report -> inward-outward):
 *   125 Inward Report, 126 Outward Report
 */
return new class extends Migration
{
    private const SOURCE_MODULE = 'inward-outward-report';
    private const TARGET_MODULE = 'inward-outward';
    private const CATEGORY_KEY = 'reports';
    private const MENU_IDS = [125, 126];

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
