<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories')
            || ! Schema::hasColumn('fees_menu_categories', 'module_name')) {
            return;
        }

        DB::table('fees_menu_categories')
            ->where('module_name', 'teach_learn')
            ->where('category_key', 'process-builder')
            ->update([
                'route' => '/general/add_process',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories')
            || ! Schema::hasColumn('fees_menu_categories', 'module_name')) {
            return;
        }

        DB::table('fees_menu_categories')
            ->where('module_name', 'teach_learn')
            ->where('category_key', 'process-builder')
            ->update([
                'route' => '/teach-learn/process-builder',
                'updated_at' => now(),
            ]);
    }
};
