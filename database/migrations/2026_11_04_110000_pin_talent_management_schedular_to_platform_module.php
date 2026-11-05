<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Talent Management "Schedular" tab (`/modules/talent-management/schedular`) to the `talent`
 * module of the central Platform Scheduler, so it lists that module's tasks (interviews soon, postings
 * closing, exits due).
 *
 *   php artisan migrate --path=database/migrations/2026_11_04_110000_pin_talent_management_schedular_to_platform_module.php
 */
return new class extends Migration
{
    private const PLATFORM_MODULE_KEY = 'talent';

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories') || ! Schema::hasColumn('fees_menu_categories', 'platform_module_key')) {
            return;
        }

        DB::table('fees_menu_categories')
            ->where('module_name', 'talent-management')->where('category_key', 'schedular')
            ->update(['platform_module_key' => self::PLATFORM_MODULE_KEY, 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories') || ! Schema::hasColumn('fees_menu_categories', 'platform_module_key')) {
            return;
        }

        DB::table('fees_menu_categories')
            ->where('module_name', 'talent-management')->where('category_key', 'schedular')
            ->update(['platform_module_key' => null, 'updated_at' => now()]);
    }
};
