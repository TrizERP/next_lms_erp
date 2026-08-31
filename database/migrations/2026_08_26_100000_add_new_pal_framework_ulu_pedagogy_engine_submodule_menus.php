<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers New PAL's remaining three level-3 sub-modules — Framework,
 * Unified Learning Units and Pedagogy Engine — which the DashboardShell tab
 * bar has shown since launch but which were never given tblmenumaster rows,
 * so Access Roles had nothing to grant/revoke `can_view` on for them (unlike
 * their siblings Content Model/Administration/Gamification, added by
 * 2026_08_14_150000 / 2026_08_14_160100 / 2026_08_17_110000).
 *
 * Also renumbers all six New PAL children's sort_order to match the tab
 * bar's display order (Framework, Content Model, Unified Learning Units,
 * Pedagogy Engine, Administration, Gamification), since the three new rows
 * are inserted at their intended positions rather than appended at the end.
 *
 * Rights are mirrored from the New PAL parent itself (not from a sibling
 * sub-module), which currently grants Teacher/LMS Teacher/Admin/Student —
 * preserving today's de-facto behavior where every one of those roles sees
 * all six tabs (the tab bar was unconditional). This intentionally does NOT
 * mirror the narrower Teacher/LMS Teacher/Admin-only grants that Content
 * Model/Administration/Gamification carry, since that would immediately hide
 * these three from students. Admins can subsequently revoke `can_view` per
 * role from Access Roles.
 *
 * Idempotent, and narrow: only touches the New PAL branch of tblmenumaster
 * and its tblgroupwise_rights rows.
 */
return new class extends Migration
{
    private const SUB_MODULES = [
        ['name' => 'Framework', 'link' => 'new_pal.frameworks', 'icon' => 'mdi mdi-sitemap-outline', 'description' => 'The competency/standards framework backing New PAL content.', 'sort_order' => 1],
        ['name' => 'Content Model', 'sort_order' => 2],
        ['name' => 'Unified Learning Units', 'link' => 'new_pal.ulu', 'icon' => 'mdi mdi-view-module-outline', 'description' => 'Unified Learning Units — the standard instructional unit New PAL content is organized into.', 'sort_order' => 3],
        ['name' => 'Pedagogy Engine', 'link' => 'new_pal.pedagogy_engine', 'icon' => 'mdi mdi-lightbulb-on-outline', 'description' => 'The pedagogy/recommendation engine surfaced per chapter in New PAL.', 'sort_order' => 4],
        ['name' => 'Administration', 'sort_order' => 5],
        ['name' => 'Gamification', 'sort_order' => 6],
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
                'description' => $subModule['description'],
                'parent_menu_id' => $parent->id,
                'level' => 3,
                'status' => 1,
                'sort_order' => $subModule['sort_order'],
                'link' => $subModule['link'],
                'icon' => $subModule['icon'],
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

        $links = ['new_pal.frameworks', 'new_pal.ulu', 'new_pal.pedagogy_engine'];

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
