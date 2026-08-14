<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers the "New PAL" module under LMS + PAL → Homework.
 *
 * SUPERSEDED by 2026_08_14_130000_move_new_pal_under_lms_pal.php, which moves
 * the row up to level 2 directly under LMS + PAL. That migration is
 * authoritative for the placement and handles both the "already created here"
 * and "does not exist yet" cases, so this file is left as-is rather than
 * rewritten — it is the historical record of where the row started.
 *
 * The sidebar renders exactly three levels (level 1 module → level 2 group →
 * level 3 entry — see app/data/menuMappers.ts buildMenuTree). New PAL's
 * sub-modules — Content Model first — are navigated from inside the New PAL
 * workspace itself rather than as sidebar rows.
 *
 * Tenancy and client visibility are COPIED from the Homework parent rather than
 * restated, so New PAL is visible to exactly the institutes that can already
 * see Homework, and stays in sync if that list is edited later.
 */
return new class extends Migration
{
    private const LINK = 'new_pal.index';
    private const PARENT_NAME = 'Homework';

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        // Idempotent: a re-run must not create a second copy of the menu item.
        if (DB::table('tblmenumaster')->where('link', self::LINK)->exists()) {
            return;
        }

        $parent = $this->resolveHomeworkParent();
        if ($parent === null) {
            // No Homework group on this estate — nothing to hang New PAL from.
            // The migration stays green so a fresh install is not blocked; the
            // route still works, it just has no sidebar entry until Homework exists.
            return;
        }

        $nextSort = (int) DB::table('tblmenumaster')
            ->where('parent_menu_id', $parent->id)
            ->max('sort_order');

        DB::table('tblmenumaster')->insert([
            'name' => 'New PAL',
            'menu_title' => $parent->menu_title ?: 'LMS',
            'description' => 'New PAL — Content Intelligence workspace',
            'parent_menu_id' => $parent->id,
            'level' => 3,
            'status' => 1,
            'sort_order' => $nextSort + 1,
            'link' => self::LINK,
            'icon' => 'mdi mdi-brain',
            'sub_institute_id' => $parent->sub_institute_id,
            'client_id' => $parent->client_id,
            'menu_type' => 'ENTRY',
            'site_map_name' => 'New PAL',
            'menu_path' => 'New PAL',
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('tblmenumaster')) {
            DB::table('tblmenumaster')->where('link', self::LINK)->delete();
        }
    }

    /**
     * The Homework group the app actually renders: a level-2 row named
     * "Homework" that already has level-3 children. Several legacy "HomeWork"
     * rows exist with status 0 or no children; picking the one with children
     * avoids attaching New PAL to a dead branch.
     */
    private function resolveHomeworkParent(): ?object
    {
        $candidates = DB::table('tblmenumaster')
            ->whereRaw('LOWER(name) = ?', [strtolower(self::PARENT_NAME)])
            ->where('level', 2)
            ->where('status', 1)
            ->get();

        foreach ($candidates as $candidate) {
            $childCount = DB::table('tblmenumaster')
                ->where('parent_menu_id', $candidate->id)
                ->where('status', 1)
                ->count();

            if ($childCount > 0) {
                return $candidate;
            }
        }

        return $candidates->first();
    }
};
