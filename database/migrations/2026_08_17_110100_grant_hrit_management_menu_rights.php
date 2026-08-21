<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grants view/manage rights on the new "HRIT Management" menu tree (module +
 * 3 menus + 12 submenus, inserted by
 * 2026_08_17_110000_add_hrit_management_menu.php) so it actually renders.
 *
 * A tblmenumaster row alone is not enough — MenuMiddleware builds the
 * sidebar by joining tblmenumaster against tblgroupwise_rights (by profile)
 * and tblindividual_rights (by user); a menu with no rights row is invisible
 * to everyone, including admins (see 2026_08_14_120000_grant_new_pal_menu_rights.php
 * for precedent).
 *
 * Unlike New PAL (which had a sibling menu under "Homework" to derive grants
 * from), HRIT Management is a brand-new top-level module with no sibling to
 * copy from. The rest of the HR/Leave/Payroll codebase (HrmsController,
 * LeaveReportController, LeaveSummaryReportController, Helper.php) already
 * gates HR-adjacent access by profile NAME, using the same fixed list:
 * ["Admin", "Super Admin", "School Admin", "Assistant Admin"]. This
 * migration reuses that exact convention rather than a numeric profile_id
 * (which is per-tenant/not stable — see tbluserprofilemaster), so every
 * admin-equivalent profile on every institute that already exists gets
 * view+manage rights on HRIT Management out of the box.
 */
return new class extends Migration
{
    private const MODULE_LINK = 'hrit_management.index';

    private const ADMIN_PROFILE_NAMES = ['Admin', 'Super Admin', 'School Admin', 'Assistant Admin'];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')
            || ! Schema::hasTable('tblgroupwise_rights')
            || ! Schema::hasTable('tbluserprofilemaster')
        ) {
            return;
        }

        $menuIds = $this->hritMenuIds();
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

        $menuIds = $this->hritMenuIds();
        if ($menuIds !== []) {
            DB::table('tblgroupwise_rights')->whereIn('menu_id', $menuIds)->delete();
        }
    }

    private function hritMenuIds(): array
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
            $subIds = DB::table('tblmenumaster')->where('parent_menu_id', $menuId)->pluck('id')->all();
            $menuIds = array_merge($menuIds, $subIds);
        }

        return $menuIds;
    }
};
