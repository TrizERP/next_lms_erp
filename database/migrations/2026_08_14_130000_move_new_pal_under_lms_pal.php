<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves "New PAL" from LMS + PAL → Homework up to LMS + PAL directly.
 *
 * Final structure:  LMS + PAL (level 1) → New PAL (level 2) → /pal/new
 *
 * New PAL is a workspace rather than a menu group, so it is a level-2 LEAF that
 * links straight to its landing page — the same shape as the other childless
 * level-2 rows in this group (Chapter Master, Lo Master, Add Topic …), not the
 * `javascript:void(0);` shape used by groups like Homework and Test.
 *
 * Its sub-modules — Content Model first — stay inside the workspace. Level 3 is
 * now free if they should also appear in the sidebar later; that is a separate,
 * deliberate decision rather than something this migration assumes.
 *
 * This migration is authoritative for the placement and is idempotent: it moves
 * the row if the earlier migration already created it under Homework, and
 * creates it in the right place if it does not exist at all. Visibility is
 * DELIBERATELY NOT WIDENED — the existing grants are carried over untouched, so
 * moving the item in the tree does not silently expose it to profiles that
 * could not see it before.
 */
return new class extends Migration
{
    private const LINK = 'new_pal.index';
    private const PARENT_NAME = 'LMS + PAL';

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $parent = DB::table('tblmenumaster')
            ->whereRaw('LOWER(name) = ?', [strtolower(self::PARENT_NAME)])
            ->where('level', 1)
            ->where('status', 1)
            ->first();

        if ($parent === null) {
            return;
        }

        // Sit at the end of the group rather than displacing anything.
        $nextSort = (int) DB::table('tblmenumaster')
            ->where('parent_menu_id', $parent->id)
            ->max('sort_order');

        $existing = DB::table('tblmenumaster')->where('link', self::LINK)->first();

        if ($existing !== null) {
            DB::table('tblmenumaster')->where('id', $existing->id)->update([
                'parent_menu_id' => $parent->id,
                'level' => 2,
                'sort_order' => $nextSort + 1,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('tblmenumaster')->insert([
            'name' => 'New PAL',
            'menu_title' => $parent->menu_title ?: 'LMS',
            'description' => 'New PAL — Content Intelligence workspace',
            'parent_menu_id' => $parent->id,
            'level' => 2,
            'status' => 1,
            'sort_order' => $nextSort + 1,
            'link' => self::LINK,
            'icon' => 'mdi mdi-brain',
            // Scoped to the institutes that hold extracted chapter data. Copied
            // from a live sibling rather than invented, so it tracks whatever
            // this estate actually uses.
            'sub_institute_id' => $this->siblingScope($parent->id, 'sub_institute_id') ?? '1',
            'client_id' => $this->siblingScope($parent->id, 'client_id'),
            'menu_type' => 'ENTRY',
            'site_map_name' => 'New PAL',
            'menu_path' => 'New PAL',
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        // Put it back under Homework, where the previous migration placed it.
        $homework = DB::table('tblmenumaster')
            ->whereRaw('LOWER(name) = ?', ['homework'])
            ->where('level', 2)
            ->where('status', 1)
            ->first();

        if ($homework !== null) {
            DB::table('tblmenumaster')->where('link', self::LINK)->update([
                'parent_menu_id' => $homework->id,
                'level' => 3,
                'updated_at' => now(),
            ]);
        }
    }

    /** A live sibling's tenancy/client scope, so a fresh insert matches the estate. */
    private function siblingScope(int $parentId, string $column): ?string
    {
        $value = DB::table('tblmenumaster')
            ->where('parent_menu_id', $parentId)
            ->where('status', 1)
            ->whereNotNull($column)
            ->orderBy('id')
            ->value($column);

        return $value === null ? null : (string) $value;
    }
};
