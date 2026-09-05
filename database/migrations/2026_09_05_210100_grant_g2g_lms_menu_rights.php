<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grants view/manage rights on the LMS module row and its 9 screens
 * (inserted by 2026_09_05_210000_add_g2g_lms_menu.php) so they actually
 * render — a tblmenumaster row alone is invisible to everyone
 * (MenuMiddleware joins tblmenumaster against tblgroupwise_rights).
 *
 * Same convention as 2026_08_21_120100_grant_competency_management_menu_rights.php
 * (itself following 2026_08_18_120100_grant_talent_management_menu_rights.php):
 * granted to the same admin-equivalent profile names already established for
 * Talent Management / Competency Management / HRIT, so these menus are
 * visible to whoever can already see the rest of the People & Competency
 * area. Packages 1-4 may extend this to non-admin profiles (e.g. Teacher,
 * Staff) per-screen once the actual audience for each of the 9 screens is
 * finalised — this migration only restores parity with its sibling modules.
 */
return new class extends Migration
{
    private const MENU_LINKS = [
        'g2g_lms.index',
        'g2g_lms.learning_dashboard',
        'g2g_lms.learning_catalog',
        'g2g_lms.my_learning',
        'g2g_lms.assignments',
        'g2g_lms.sessions_calendar',
        'g2g_lms.certifications_records',
        'g2g_lms.course_builder',
        'g2g_lms.administration_governance',
        'g2g_lms.assessments',
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

        $menuIds = $this->g2gLmsMenuIds();
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

        $menuIds = $this->g2gLmsMenuIds();
        if ($menuIds !== []) {
            DB::table('tblgroupwise_rights')->whereIn('menu_id', $menuIds)->delete();
        }
    }

    private function g2gLmsMenuIds(): array
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return [];
        }

        return DB::table('tblmenumaster')->whereIn('link', self::MENU_LINKS)->pluck('id')->all();
    }
};
