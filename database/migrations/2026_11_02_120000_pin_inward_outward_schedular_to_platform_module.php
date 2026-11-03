<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Inward Outward "Schedular" tab (`/modules/inward-outward/schedular`) to the
 * `inward_outward` module of the central Platform Scheduler, so the tab lists that
 * module's scheduled tasks (daily register digest, unfiled-entry scans).
 */
return new class extends Migration
{
    private const PLATFORM_MODULE_KEY = 'inward_outward';
    private const MODULES = ['inward-outward', 'inward-outward-report'];

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories') || ! Schema::hasColumn('fees_menu_categories', 'platform_module_key')) {
            return;
        }

        DB::table('fees_menu_categories')
            ->whereIn('module_name', self::MODULES)
            ->where('category_key', 'schedular')
            ->update([
                'platform_module_key' => self::PLATFORM_MODULE_KEY,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories') || ! Schema::hasColumn('fees_menu_categories', 'platform_module_key')) {
            return;
        }

        DB::table('fees_menu_categories')
            ->whereIn('module_name', self::MODULES)
            ->where('category_key', 'schedular')
            ->update(['platform_module_key' => null, 'updated_at' => now()]);
    }
};
