<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hides the Utility module's "Reports" tab (`/modules/utility/reports`): Utility has no report
 * screens, so the tab only showed "Nothing here yet". It is hidden only while it is still empty.
 */
return new class extends Migration
{
    private const MODULE = 'utility';

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            return;
        }

        $hasMenus = Schema::hasTable('fees_menu_category_items')
            && DB::table('fees_menu_category_items')
                ->where('module_name', self::MODULE)->where('category_key', 'reports')
                ->where('status', 1)->exists();

        if (! $hasMenus) {
            DB::table('fees_menu_categories')
                ->where('module_name', self::MODULE)->where('category_key', 'reports')
                ->update(['status' => 0, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            return;
        }

        DB::table('fees_menu_categories')
            ->where('module_name', self::MODULE)->where('category_key', 'reports')
            ->update(['status' => 1, 'updated_at' => now()]);
    }
};
