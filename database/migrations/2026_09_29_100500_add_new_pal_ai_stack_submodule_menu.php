<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers New PAL → AI Stack as a level-3 sub-module of New PAL, mirroring
 * 2026_08_17_110000_add_new_pal_gamification_submodule_menu.php exactly.
 *
 * WHY THE PAGE ALREADY EXISTED BUT WAS UNREACHABLE
 *
 * `app/pal/new/ai-stack/page.tsx` and its nine screens have existed since New PAL's AI
 * Stack was built — the same working page Exam and every other module's AI Stack uses.
 * But New PAL's sub-nav is NOT auto-derived from `tblmenumaster` the way a plain
 * module's level-3 bar is: `DashboardShell`'s `NEW_PAL_LEVEL3_ITEMS` is a hardcoded
 * frontend list of {label, href}, filtered down to whichever of those labels also
 * appears as a `tblmenumaster` child of New PAL (id 531) that the caller's role can
 * view — see `newPalLevel3Items()`'s own doc comment. AI Stack was in neither list, so
 * the page existed, worked, and had no tab that could ever reach it.
 *
 * This migration adds the menu row half. The frontend list half is added in the same
 * commit, in `app/components/DashboardShell.tsx`.
 *
 * IDEMPOTENT, matched on `link` rather than name, and narrow: it touches nothing but
 * its own row plus the rights it mirrors from the New PAL parent menu.
 */
return new class extends Migration
{
    private const SUB_MODULE = [
        'name' => 'AI Stack',
        'link' => 'new_pal.ai_stack',
        'icon' => 'mdi mdi-robot-outline',
        'description' => 'AI services and automation for New PAL — the same nine tabs every other module\'s AI Stack has.',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $parent = DB::table('tblmenumaster')
            ->whereRaw('LOWER(name) = ?', ['new pal'])
            ->where('level', 2)
            ->orderBy('id')
            ->first();

        if ($parent === null) {
            return;
        }

        if (DB::table('tblmenumaster')->where('link', self::SUB_MODULE['link'])->exists()) {
            return;
        }

        $sortOrder = ((int) DB::table('tblmenumaster')
            ->where('parent_menu_id', $parent->id)
            ->max('sort_order')) + 1;

        $menuId = DB::table('tblmenumaster')->insertGetId([
            'name' => self::SUB_MODULE['name'],
            'menu_title' => $parent->menu_title,
            'description' => self::SUB_MODULE['description'],
            'parent_menu_id' => $parent->id,
            'level' => 3,
            'status' => 1,
            'sort_order' => $sortOrder,
            'link' => self::SUB_MODULE['link'],
            'icon' => self::SUB_MODULE['icon'],
            'sub_institute_id' => $parent->sub_institute_id,
            'client_id' => $parent->client_id,
            'menu_type' => 'ENTRY',
            'site_map_name' => self::SUB_MODULE['name'],
            'menu_path' => self::SUB_MODULE['name'],
            'created_at' => now(),
        ]);

        $this->mirrorRights((int) $parent->id, (int) $menuId);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $menuId = DB::table('tblmenumaster')->where('link', self::SUB_MODULE['link'])->value('id');

        if ($menuId === null) {
            return;
        }

        if (Schema::hasTable('tblgroupwise_rights')) {
            DB::table('tblgroupwise_rights')->where('menu_id', $menuId)->delete();
        }

        DB::table('tblmenumaster')->where('id', $menuId)->delete();
    }

    /** A sub-module is visible to exactly the profiles its parent module is. */
    private function mirrorRights(int $parentMenuId, int $menuId): void
    {
        if (! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }

        foreach (DB::table('tblgroupwise_rights')->where('menu_id', $parentMenuId)->get() as $grant) {
            $exists = DB::table('tblgroupwise_rights')
                ->where('menu_id', $menuId)
                ->where('profile_id', $grant->profile_id)
                ->where('sub_institute_id', $grant->sub_institute_id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('tblgroupwise_rights')->insert([
                'menu_id' => $menuId,
                'profile_id' => $grant->profile_id,
                'sub_institute_id' => $grant->sub_institute_id,
                'can_view' => $grant->can_view,
                'can_add' => $grant->can_add,
                'can_edit' => $grant->can_edit,
                'can_delete' => $grant->can_delete,
                'created_at' => now(),
            ]);
        }
    }
};
