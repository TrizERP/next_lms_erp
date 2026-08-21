<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grants view/manage rights on the 3 new Competency Management menus
 * (inserted by 2026_08_21_120000_add_competency_management_menu.php) so they
 * actually render - a tblmenumaster row alone is invisible to everyone
 * (MenuMiddleware joins tblmenumaster against tblgroupwise_rights).
 *
 * Same convention as 2026_08_18_120100_grant_talent_management_menu_rights.php:
 * granted to the same admin-equivalent profile names already established for
 * Talent Management / HRIT, so these menus are visible to whoever can already
 * see the rest of Talent Management.
 */
return new class extends Migration
{
    private const MENU_LINKS = [
        'talent.employee_profiles',
        'talent.certifications',
        'talent.development_career_paths',
    ];

    private const ADMIN_PROFILE_NAMES = ['Admin', 'Super Admin', 'School Admin', 'Assistant Admin'];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')
            || ! Schema::hasTable('tblgroupwise_rights')
            || ! Schema::hasTable('tbluserprofilemaster')
        ) {
            return;
        }

        $menuIds = $this->competencyMenuIds();
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

        $menuIds = $this->competencyMenuIds();
        if ($menuIds !== []) {
            DB::table('tblgroupwise_rights')->whereIn('menu_id', $menuIds)->delete();
        }
    }

    private function competencyMenuIds(): array
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return [];
        }

        return DB::table('tblmenumaster')->whereIn('link', self::MENU_LINKS)->pluck('id')->all();
    }
};
