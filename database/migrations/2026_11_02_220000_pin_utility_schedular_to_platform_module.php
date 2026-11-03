<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Utility "Schedular" tab (`/modules/utility/schedular`) to the `utility` module of the
 * central Platform Scheduler, so the tab lists that module's scheduled tasks (rollover readiness
 * and progress, custom module summary).
 */
return new class extends Migration
{
    private const MODULE = 'utility';
    private const PLATFORM_MODULE_KEY = 'utility';

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
