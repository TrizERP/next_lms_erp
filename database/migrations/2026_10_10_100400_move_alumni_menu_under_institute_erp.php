<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves "Alumni Management" from a top-level sidebar module to a module inside
 * "Institute ERP", beside Student, Fees, Hostel and the rest: it becomes a level-2
 * container (link javascript:void(0);, like Hostel) and its screens become level 3.
 *
 * Menu ids are unchanged, so the rights granted by 2026_10_10_100200 (keyed on menu_id)
 * keep working with no new grants. Idempotent: the module is found either by its original
 * link or, once moved, by name under Institute ERP; a second run changes nothing.
 * Forward-only down(): moving it back would reintroduce the hidden top-level entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $parent = DB::table('tblmenumaster')->where('level', 1)->where('parent_menu_id', 0)->where('name', 'Institute ERP')->first();
        if ($parent === null) {
            return;
        }

        $module = DB::table('tblmenumaster')->where('link', 'alumni_management.index')->first()
            ?? DB::table('tblmenumaster')->where('name', 'Alumni Management')->where('level', 2)->where('parent_menu_id', $parent->id)->first();
        if ($module === null) {
            return;
        }

        $alreadyMoved = (int) $module->level === 2 && (int) $module->parent_menu_id === (int) $parent->id;
        if (! $alreadyMoved) {
            DB::table('tblmenumaster')->where('id', $module->id)->update([
                'level' => 2,
                'parent_menu_id' => $parent->id,
                'link' => 'javascript:void(0);',
                'menu_title' => null,
                'sort_order' => ((int) DB::table('tblmenumaster')->where('parent_menu_id', $parent->id)->max('sort_order')) + 1,
            ]);
        }

        DB::table('tblmenumaster')->where('parent_menu_id', $module->id)->update(['level' => 3, 'menu_title' => 'Alumni Management']);
    }

    public function down(): void
    {
        // Forward-only - see class doc-block.
    }
};
