<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Alumni Management "Workflow" tab (`/modules/alumni-management/workflow`) to the
 * `alumni` module of the central Platform Workflow console, which now declares three approval
 * flows (alumni verification, job or internship posting, donation verification).
 */
return new class extends Migration
{
    private const MODULE = 'alumni-management';
    private const PLATFORM_MODULE_KEY = 'alumni';

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories') || ! Schema::hasColumn('fees_menu_categories', 'platform_module_key')) {
            return;
        }

        DB::table('fees_menu_categories')
            ->where('module_name', self::MODULE)->where('category_key', 'workflow')
            ->update(['platform_module_key' => self::PLATFORM_MODULE_KEY, 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories') || ! Schema::hasColumn('fees_menu_categories', 'platform_module_key')) {
            return;
        }

        DB::table('fees_menu_categories')
            ->where('module_name', self::MODULE)->where('category_key', 'workflow')
            ->update(['platform_module_key' => null, 'updated_at' => now()]);
    }
};
