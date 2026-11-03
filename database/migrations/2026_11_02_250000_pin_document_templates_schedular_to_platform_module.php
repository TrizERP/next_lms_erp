<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the Document Templates "Schedular" tab (`/modules/document-templates/schedular`) to its own
 * `document_templates` module of the central Platform Scheduler. It was pinned to `compliance`,
 * which lists tasks of a different module.
 */
return new class extends Migration
{
    private const MODULE = 'document-templates';
    private const PLATFORM_MODULE_KEY = 'document_templates';

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
            ->update(['platform_module_key' => 'compliance', 'updated_at' => now()]);
    }
};
