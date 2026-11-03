<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Organization Management "Schedular" tab (`/modules/organization-management/schedular`)
 * to the `organization_management` module of the central Platform Scheduler, so it lists that
 * module's tasks (compliance due and overdue, disciplinary incidents this month).
 *
 *   php artisan migrate --path=database/migrations/2026_11_03_120000_pin_organization_management_schedular_to_platform_module.php
 */
return new class extends Migration
{
    private const MODULE = 'organization-management';
    private const PLATFORM_MODULE_KEY = 'organization_management';

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories') || ! Schema::hasColumn('fees_menu_categories', 'platform_module_key')) {
            return;
        }

        DB::table('fees_menu_categories')
            ->where('module_name', self::MODULE)->where('category_key', 'schedular')
            ->update(['platform_module_key' => self::PLATFORM_MODULE_KEY, 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories') || ! Schema::hasColumn('fees_menu_categories', 'platform_module_key')) {
            return;
        }

        DB::table('fees_menu_categories')
            ->where('module_name', self::MODULE)->where('category_key', 'schedular')
            ->update(['platform_module_key' => null, 'updated_at' => now()]);
    }
};
