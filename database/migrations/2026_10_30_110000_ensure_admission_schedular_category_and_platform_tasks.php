<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures the Schedular category exists for the Admission module in `fees_menu_categories`,
 * and pins its `platform_module_key` to 'admissions'.
 *
 * This connects the top-bar "Schedular" tab in the Admission module directly to the
 * central Platform Scheduler console scoped to admissions tasks (enquiry follow-ups,
 * registration reviews, and confirmation reminders).
 */
return new class extends Migration
{
    private const CATEGORY_KEY = 'schedular';
    private const PLATFORM_MODULE_KEY = 'admissions';
    private const SORT_ORDER = 12;

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            return;
        }

        $now = now();

        // 1. Find the level2_menu_id for Admission from existing admission category rows or tblmenumaster
        $level2MenuId = DB::table('fees_menu_categories')
            ->where('module_name', 'admission')
            ->whereNotNull('level2_menu_id')
            ->value('level2_menu_id');

        if (! $level2MenuId && Schema::hasTable('tblmenumaster')) {
            $level2MenuId = DB::table('tblmenumaster')
                ->where('menu_type', 'level_2')
                ->where(function ($q) {
                    $q->where('name', 'Admission')
                      ->orWhere('name', 'Admissions')
                      ->orWhere('link', 'like', '%admission%');
                })
                ->value('id');
        }

        // 2. Ensure row for module_name = 'admission', category_key = 'schedular'
        $existing = DB::table('fees_menu_categories')
            ->where('module_name', 'admission')
            ->where('category_key', self::CATEGORY_KEY)
            ->first();

        if ($existing) {
            DB::table('fees_menu_categories')
                ->where('id', $existing->id)
                ->update([
                    'platform_module_key' => self::PLATFORM_MODULE_KEY,
                    'status' => 1,
                    'route' => '/modules/admission/' . self::CATEGORY_KEY,
                    'updated_at' => $now,
                ]);
        } else {
            DB::table('fees_menu_categories')->insert([
                'module_name' => 'admission',
                'level2_menu_id' => $level2MenuId,
                'category_key' => self::CATEGORY_KEY,
                'label' => 'Schedular',
                'description' => 'Scheduled tasks for Admission.',
                'route' => '/modules/admission/' . self::CATEGORY_KEY,
                'sort_order' => self::SORT_ORDER,
                'status' => 1,
                'platform_module_key' => self::PLATFORM_MODULE_KEY,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 3. Also update module_name = 'admissions' if present
        DB::table('fees_menu_categories')
            ->where('module_name', 'admissions')
            ->where('category_key', self::CATEGORY_KEY)
            ->update([
                'platform_module_key' => self::PLATFORM_MODULE_KEY,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            return;
        }

        DB::table('fees_menu_categories')
            ->where('module_name', 'admission')
            ->where('category_key', self::CATEGORY_KEY)
            ->update([
                'platform_module_key' => null,
                'updated_at' => now(),
            ]);
    }
};

