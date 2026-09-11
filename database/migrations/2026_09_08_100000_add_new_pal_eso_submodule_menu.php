<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers ESO (the Adaptive Learning Engine) as New PAL's seventh level-3
 * sub-module.
 *
 * ESO is not a new build. app/pal/eso/{page,chapter,knowledge-map,mastery} have
 * shipped, EsoEngineController serves them, and AdaptiveLearningButton is
 * already wired into StudentDashboard — but ESO was never given a
 * tblmenumaster row, so it is absent from the DashboardShell tab bar and
 * Access Roles has nothing to grant or revoke `can_view` on for it. The
 * feature was reachable only from the student dashboard button.
 *
 * Follows 2026_08_26_100000 exactly: the same `new_pal.<sub_module>` link
 * convention, the same parent lookup, and the same rights mirroring. Appended
 * at sort_order 7 rather than renumbering, since that migration already fixed
 * 1-6 to match the tab bar's display order.
 *
 * Rights are mirrored from the New PAL parent, which grants Teacher / LMS
 * Teacher / Admin / Student. Student matters here in a way it did not for
 * ESO's siblings: ESO is the one sub-module whose primary audience IS the
 * student, so inheriting the narrower Teacher/Admin-only grants that Content
 * Model and Administration carry would hide the feature from the people it
 * was built for. Admins can still revoke per role from Access Roles.
 *
 * Idempotent, and narrow: only the New PAL branch of tblmenumaster is touched.
 */
return new class extends Migration
{
    private const LINK = 'new_pal.eso';

    private const SUB_MODULE = [
        'name' => 'ESO',
        'icon' => 'mdi mdi-brain',
        'description' => 'Adaptive Learning Engine — the per-concept diagnostic, practice and mastery loop.',
        'sort_order' => 7,
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

        $existing = DB::table('tblmenumaster')
            ->where('parent_menu_id', $parent->id)
            ->whereRaw('LOWER(name) = ?', ['eso'])
            ->first();

        if ($existing !== null) {
            DB::table('tblmenumaster')->where('id', $existing->id)->update([
                'sort_order' => self::SUB_MODULE['sort_order'],
                'link' => self::LINK,
                'updated_at' => now(),
            ]);

            $this->mirrorRights((int) $parent->id, (int) $existing->id);

            return;
        }

        $menuId = DB::table('tblmenumaster')->insertGetId([
            'name' => self::SUB_MODULE['name'],
            'menu_title' => $parent->menu_title,
            'description' => self::SUB_MODULE['description'],
            'parent_menu_id' => $parent->id,
            'level' => 3,
            'status' => 1,
            'sort_order' => self::SUB_MODULE['sort_order'],
            'link' => self::LINK,
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

        $menuIds = DB::table('tblmenumaster')->where('link', self::LINK)->pluck('id');

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
