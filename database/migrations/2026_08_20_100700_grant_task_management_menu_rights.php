<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grants view/manage rights on the new "Task Management" menu tree (module +
 * 7 level-2 rows, including Administration, + 4 level-3 rows under
 * Administration, inserted by 2026_08_20_100600_add_task_management_menu.php)
 * so it actually renders.
 *
 * A tblmenumaster row alone is not enough — MenuMiddleware builds the
 * sidebar by joining tblmenumaster against tblgroupwise_rights (by profile)
 * and tblindividual_rights (by user); a menu with no rights row is invisible
 * to everyone, including admins.
 *
 * Modelled exactly on `2026_08_18_120100_grant_talent_management_menu_rights.php`,
 * but `taskManagementMenuIds()` walks all 3 levels (module → level-2
 * children → level-3 grandchildren under Administration) instead of 2.
 */
return new class extends Migration
{
    private const MODULE_LINK = 'task_management.index';

    private const ADMIN_PROFILE_NAMES = ['Admin', 'Super Admin', 'School Admin', 'Assistant Admin'];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')
            || ! Schema::hasTable('tblgroupwise_rights')
            || ! Schema::hasTable('tbluserprofilemaster')
        ) {
            return;
        }

        $menuIds = $this->taskManagementMenuIds();
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

        $menuIds = $this->taskManagementMenuIds();
        if ($menuIds !== []) {
            DB::table('tblgroupwise_rights')->whereIn('menu_id', $menuIds)->delete();
        }
    }

    /** Module (level 1) + all level-2 children + all level-3 grandchildren. */
    private function taskManagementMenuIds(): array
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return [];
        }

        $module = DB::table('tblmenumaster')->where('link', self::MODULE_LINK)->first();
        if ($module === null) {
            return [];
        }

        $menuIds = [$module->id];

        $level2MenuIds = DB::table('tblmenumaster')->where('parent_menu_id', $module->id)->pluck('id');
        foreach ($level2MenuIds as $level2MenuId) {
            $menuIds[] = $level2MenuId;

            $level3MenuIds = DB::table('tblmenumaster')->where('parent_menu_id', $level2MenuId)->pluck('id');
            foreach ($level3MenuIds as $level3MenuId) {
                $menuIds[] = $level3MenuId;
            }
        }

        return $menuIds;
    }
};
