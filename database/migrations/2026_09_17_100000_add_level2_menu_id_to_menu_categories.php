<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ties each category bar to the level-2 menu it belongs to, and widens
 * `module_name` enough to hold a slug for every module.
 *
 * Until now a module was identified only by `module_name`, and the frontend
 * decided which bar to show by comparing the selected level-2 menu's *label*
 * against a hardcoded string ('fees', 'teach/learn'). That does not survive the
 * roll-out to every module: tblmenumaster has two active level-2 menus both
 * named "Task Management" (id 253 under Institute ERP, id 551 under People &
 * Competency), so a label is not an identity. `level2_menu_id` is.
 *
 * Nullable because it is a pointer to the menu tree, not a constraint on it: a
 * module whose level-2 row is later retired should lose its bar, not block the
 * row from being read. No foreign key for the same reason, and because
 * tblmenumaster is MyISAM-era schema that carries none of its own.
 */
return new class extends Migration
{
    /** Seeded module_name => the level-2 tblmenumaster.id it represents. */
    private const EXISTING = [
        'fees' => 6,
        'teach_learn' => 269,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            return;
        }

        Schema::table('fees_menu_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('fees_menu_categories', 'level2_menu_id')) {
                $table->unsignedBigInteger('level2_menu_id')->nullable()->after('module_name');
                $table->index('level2_menu_id');
            }
        });

        // 'fees' and 'teach_learn' predate the slug convention and keep their
        // names; only their pointer is filled in.
        foreach (self::EXISTING as $moduleName => $level2MenuId) {
            DB::table('fees_menu_categories')
                ->where('module_name', $moduleName)
                ->update(['level2_menu_id' => $level2MenuId]);
        }

        // 32 characters fits every slug in use today but leaves no headroom —
        // 'organization-management' is already 23. Widened once, here, rather
        // than discovering the limit through a truncated insert later.
        DB::statement('ALTER TABLE fees_menu_categories MODIFY module_name VARCHAR(64) NOT NULL');

        if (Schema::hasTable('fees_menu_category_items')) {
            DB::statement('ALTER TABLE fees_menu_category_items MODIFY module_name VARCHAR(64) NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            return;
        }

        Schema::table('fees_menu_categories', function (Blueprint $table) {
            if (Schema::hasColumn('fees_menu_categories', 'level2_menu_id')) {
                $table->dropIndex(['level2_menu_id']);
                $table->dropColumn('level2_menu_id');
            }
        });
    }
};
