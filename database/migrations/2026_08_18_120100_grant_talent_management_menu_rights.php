<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grants view/manage rights on the new "Talent Management" menu tree (module
 * + 8 menus, inserted by 2026_08_18_120000_add_talent_management_menu.php)
 * so it actually renders.
 *
 * A tblmenumaster row alone is not enough — MenuMiddleware builds the
 * sidebar by joining tblmenumaster against tblgroupwise_rights (by profile)
 * and tblindividual_rights (by user); a menu with no rights row is invisible
 * to everyone, including admins.
 *
 * Same convention as
 * 2026_08_17_110100_grant_hrit_management_menu_rights.php: Talent Management
 * is also a brand-new top-level module with no sibling to derive grants
 * from, so this reuses the same fixed admin-equivalent profile-name list
 * already established for HRIT (there is no separate "HR" profile in
 * LMS-K12), rather than a numeric profile_id (which is per-tenant/not
 * stable — see tbluserprofilemaster).
 */
return new class extends Migration
{
    private const MODULE_LINK = 'talent_management.index';

    private const ADMIN_PROFILE_NAMES = ['Admin', 'Super Admin', 'School Admin', 'Assistant Admin'];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')
            || ! Schema::hasTable('tblgroupwise_rights')
            || ! Schema::hasTable('tbluserprofilemaster')
        ) {
            return;
        }

        $menuIds = $this->talentMenuIds();
        if ($menuIds === []) {
            return;
        }

        $profiles = DB::table('tbluserprofilemaster')
            ->select('id', 'sub_institute_id')
            ->whereIn('name', self::ADMIN_PROFILE_NAMES)
            ->where('status', 1)
            ->get();

        foreach ($menuIds as $menuId) {
            foreach ($profiles as $profile) {
                $exists = DB::table('tblgroupwise_rights')
                    ->where('menu_id', $menuId)
                    ->where('profile_id', $profile->id)
                    ->where('sub_institute_id', $profile->sub_institute_id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('tblgroupwise_rights')->insert([
                    'menu_id' => $menuId,
                    'profile_id' => $profile->id,
                    'sub_institute_id' => $profile->sub_institute_id,
                    'can_view' => 1,
                    'can_add' => 1,
                    'can_edit' => 1,
                    'can_delete' => 1,
                    'created_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }

        $menuIds = $this->talentMenuIds();
        if ($menuIds !== []) {
            DB::table('tblgroupwise_rights')->whereIn('menu_id', $menuIds)->delete();
        }
    }

    private function talentMenuIds(): array
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return [];
        }

        $module = DB::table('tblmenumaster')->where('link', self::MODULE_LINK)->first();
        if ($module === null) {
            return [];
        }

        $menuIds = [$module->id];

        $menus = DB::table('tblmenumaster')->where('parent_menu_id', $module->id)->pluck('id');
        foreach ($menus as $menuId) {
            $menuIds[] = $menuId;
        }

        return $menuIds;
    }
};
