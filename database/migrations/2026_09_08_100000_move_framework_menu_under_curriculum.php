<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the "Framework" menu row from LMS + PAL → New PAL to
 * LMS + PAL → Curriculum Planning.
 *
 * WHY
 *
 * A framework alignment cannot exist without the curriculum concept it attaches
 * to, so the curriculum owns the authoring surface and PAL reads it. Today the
 * same screen sits under New PAL, which reads as PAL owning the alignment data
 * and invites a second, divergent implementation on the curriculum side later.
 *
 * WHAT THIS DOES NOT DO
 *
 * Nothing moves on disk and no page is duplicated. `/pal/frameworks` remains the
 * one implementation, and PAL keeps reaching it — it simply no longer owns the
 * menu entry. The row's `link` (`new_pal.frameworks`) is deliberately left
 * alone: it is an internal key that `app/data/routeMapper.ts` in the lms_k12
 * frontend maps to `/pal/frameworks`, and renaming it would break that mapping
 * for no user-visible gain.
 *
 * RIGHTS ARE UNTOUCHED ON PURPOSE
 *
 * `tblgroupwise_rights` and `tblindividual_rights` key on `menu_id`, and the
 * menu id does not change here — only its parent. Every existing grant
 * therefore continues to apply, and re-granting would risk widening access
 * beyond what roles have today.
 *
 * PAIRED FRONTEND CHANGE
 *
 * `app/components/DashboardShell.tsx` (lms_k12) drops Framework from
 * NEW_PAL_LEVEL3_ITEMS in the same change. That list decides which routes New
 * PAL claims for its own tab bar; without that edit `/pal/frameworks` would
 * still wear New PAL's navigation while living under Curriculum Planning.
 *
 * Idempotent, and narrow: it touches exactly one row in tblmenumaster.
 */
return new class extends Migration
{
    private const FRAMEWORK_LINK = 'new_pal.frameworks';
    private const TARGET_PARENT = 'curriculum planning';
    private const SOURCE_PARENT = 'new pal';

    public function up(): void
    {
        $this->reparent(self::TARGET_PARENT);
    }

    /**
     * Puts it back under New PAL.
     *
     * Safe to run: this migration only ever issues UPDATE statements, so it is
     * not affected by the DROP TABLE guard in AppServiceProvider that stops
     * most rollbacks in this project part-way through.
     */
    public function down(): void
    {
        $this->reparent(self::SOURCE_PARENT);
    }

    private function reparent(string $parentName): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $framework = DB::table('tblmenumaster')
            ->where('link', self::FRAMEWORK_LINK)
            ->where('level', 3)
            ->orderBy('id')
            ->first();

        // Resolved by name rather than by id so this runs on any estate whose
        // menu ids differ from the one it was written against.
        $parent = DB::table('tblmenumaster')
            ->whereRaw('LOWER(name) = ?', [$parentName])
            ->where('level', 2)
            ->orderBy('id')
            ->first();

        if ($framework === null || $parent === null) {
            return;
        }

        if ((int) $framework->parent_menu_id === (int) $parent->id) {
            return;
        }

        // Appended rather than slotted in, so the sibling screens people already
        // know keep the positions they have always had.
        $nextSortOrder = (int) DB::table('tblmenumaster')
            ->where('parent_menu_id', $parent->id)
            ->max('sort_order') + 1;

        DB::table('tblmenumaster')
            ->where('id', $framework->id)
            ->update([
                'parent_menu_id' => $parent->id,
                'sort_order' => $nextSortOrder,
                'menu_title' => $parent->menu_title,
                'updated_at' => now(),
            ]);
    }
};
