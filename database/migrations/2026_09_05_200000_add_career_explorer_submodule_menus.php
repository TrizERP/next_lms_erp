<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives the 4 Career Explorer tabs — Find Occupation, College Profile,
 * Course Profile, Employer Profile — real tblmenumaster rows, mirroring
 * 2026_09_05_100000_add_career_awareness_submodule_menus.php for Career
 * Awareness. These are already real Next.js routes (/career-explorer,
 * /career-explorer/college, /career-explorer/courses,
 * /career-explorer/employers) rendered by CareerExplorerTabBar, but had no
 * Level-3 menu rows, so Access Roles had nothing to grant/revoke `can_view`
 * on for them.
 *
 * tblmenumaster carries TWO level-2 rows whose name collapses to "career
 * explorer" under a case-insensitive compare: id 451 "Career Explorer"
 * (legacy, link 'javascript:void(0);', already has 2 unrelated legacy
 * level-3 children) and the row with link '/career-explorer' (the one the
 * current Next.js sidebar actually resolves to — it was added alongside,
 * and with the exact same rights-grant timestamp as, the "career awareness"
 * row 2026_09_05_100000 already targets). Matching by name alone would risk
 * picking the wrong (legacy) parent, so this migration matches by `link`
 * instead.
 *
 * Idempotent, and no-ops harmlessly if no such row exists in a given
 * environment.
 */
return new class extends Migration
{
    private const PARENT_LINK = '/career-explorer';

    private const SUB_MODULES = [
        ['name' => 'Find Occupation', 'link' => 'career_explore.find_occupation', 'sort_order' => 1],
        ['name' => 'College Profile', 'link' => 'career_explore.college', 'sort_order' => 2],
        ['name' => 'Course Profile', 'link' => 'career_explore.courses', 'sort_order' => 3],
        ['name' => 'Employer Profile', 'link' => 'career_explore.employers', 'sort_order' => 4],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $parent = DB::table('tblmenumaster')
            ->where('link', self::PARENT_LINK)
            ->where('level', 2)
            ->orderBy('id')
            ->first();

        if ($parent === null) {
            return;
        }

        foreach (self::SUB_MODULES as $subModule) {
            $existing = DB::table('tblmenumaster')
                ->where('parent_menu_id', $parent->id)
                ->whereRaw('LOWER(name) = ?', [strtolower($subModule['name'])])
                ->first();

            if ($existing !== null) {
                DB::table('tblmenumaster')->where('id', $existing->id)->update([
                    'sort_order' => $subModule['sort_order'],
                    'updated_at' => now(),
                ]);
                continue;
            }

            $menuId = DB::table('tblmenumaster')->insertGetId([
                'name' => $subModule['name'],
                'menu_title' => $parent->menu_title,
                'description' => $subModule['name'],
                'parent_menu_id' => $parent->id,
                'level' => 3,
                'status' => 1,
                'sort_order' => $subModule['sort_order'],
                'link' => $subModule['link'],
                'icon' => $parent->icon,
                'sub_institute_id' => $parent->sub_institute_id,
                'client_id' => $parent->client_id,
                'menu_type' => 'ENTRY',
                'site_map_name' => $subModule['name'],
                'menu_path' => $subModule['name'],
                'created_at' => now(),
            ]);

            $this->mirrorRights((int) $parent->id, $menuId);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $links = array_column(self::SUB_MODULES, 'link');

        $menuIds = DB::table('tblmenumaster')->whereIn('link', $links)->pluck('id');

        if ($menuIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('tblgroupwise_rights')) {
            DB::table('tblgroupwise_rights')->whereIn('menu_id', $menuIds)->delete();
        }
        DB::table('tblmenumaster')->whereIn('id', $menuIds)->delete();
    }

    /** A sub-module is visible to exactly the profiles its module is. */
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
