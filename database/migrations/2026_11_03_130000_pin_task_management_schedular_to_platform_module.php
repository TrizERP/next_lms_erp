<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Task Management "Schedular" tab to the `task-management` module of the central
 * Platform Scheduler, so it lists that module's tasks (due soon, overdue, on hold).
 *
 * The level-2 menu exists per institute under slugs such as `task-management-253` and
 * `task-management-551`, so every row whose module_name starts with `task-management` is pinned.
 *
 *   php artisan migrate --path=database/migrations/2026_11_03_130000_pin_task_management_schedular_to_platform_module.php
 */
return new class extends Migration
{
    private const PLATFORM_MODULE_KEY = 'task-management';

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories') || ! Schema::hasColumn('fees_menu_categories', 'platform_module_key')) {
            return;
        }

        DB::table('fees_menu_categories')
            ->where('module_name', 'like', 'task-management%')->where('category_key', 'schedular')
            ->update(['platform_module_key' => self::PLATFORM_MODULE_KEY, 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories') || ! Schema::hasColumn('fees_menu_categories', 'platform_module_key')) {
            return;
        }

        DB::table('fees_menu_categories')
            ->where('module_name', 'like', 'task-management%')->where('category_key', 'schedular')
            ->update(['platform_module_key' => null, 'updated_at' => now()]);
    }
};
