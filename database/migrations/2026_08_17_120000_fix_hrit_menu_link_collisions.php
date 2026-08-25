<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-up to 2026_08_17_110000_add_hrit_management_menu.php.
 *
 * That migration's idempotency check (skip insert if a row with the same
 * `link` already exists) silently skipped 7 of the 12 level-3 submenus,
 * because those link values (payroll_type.index, hrms_attendance.index,
 * etc.) were already in use by unrelated, actively-visible legacy menu
 * entries elsewhere in the tree (People & Competency > Payroll/User
 * Attendance, Reports > HRMS Report — ids 351/364/372 and their children).
 * Reusing those exact link strings for the new HRIT Management rows would
 * either duplicate-fail or, once routeMapper.ts maps them, silently change
 * where those unrelated pre-existing menu items navigate to — neither is
 * acceptable ("do not modify unrelated existing module").
 *
 * Fix: give the 7 affected HRIT Management submenus fresh, collision-free
 * `hrit.*` link keys (matching the convention the original migration already
 * used for Leave management, which had no legacy route name to reuse), and
 * insert them under the existing Attendance Management (536) / Payroll
 * Management (542) parents. The pre-existing legacy menu rows are left
 * completely untouched.
 */
return new class extends Migration
{
    private const ATTENDANCE_MENU_LINK = 'hrit_attendance_management.index';
    private const PAYROLL_MENU_LINK = 'hrit_payroll_management.index';

    private const ADMIN_PROFILE_NAMES = ['Admin', 'Super Admin', 'School Admin', 'Assistant Admin'];

    private const ATTENDANCE_CHILDREN = [
        ['name' => 'Attendance Tracking', 'link' => 'hrit.attendance.tracking', 'description' => 'Record and view staff attendance', 'sort_order' => 1],
        ['name' => 'Attendance Reports', 'link' => 'hrit.attendance.reports', 'description' => 'Attendance summary and detail reports', 'sort_order' => 2],
    ];

    private const PAYROLL_CHILDREN = [
        ['name' => 'Payroll Type', 'link' => 'hrit.payroll.type', 'description' => 'Configure payroll types', 'sort_order' => 1],
        ['name' => 'Salary Structure', 'link' => 'hrit.payroll.salary-structure', 'description' => 'Employee salary structure setup', 'sort_order' => 2],
        ['name' => 'Payroll Deduction', 'link' => 'hrit.payroll.deduction', 'description' => 'Configure payroll deductions', 'sort_order' => 3],
        ['name' => 'Form 16', 'link' => 'hrit.payroll.form16', 'description' => 'Generate Form 16', 'sort_order' => 5],
        ['name' => 'Salary Certificate', 'link' => 'hrit.payroll.salary-certificate', 'description' => 'Generate salary certificates', 'sort_order' => 6],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $this->insertChildren(self::ATTENDANCE_MENU_LINK, self::ATTENDANCE_CHILDREN, 'mdi mdi-calendar-check-outline');
        $this->insertChildren(self::PAYROLL_MENU_LINK, self::PAYROLL_CHILDREN, 'mdi mdi-cash-multiple');
        $this->grantRights();
    }

    /**
     * Same profile-name convention as
     * 2026_08_17_110100_grant_hrit_management_menu_rights.php — grants
     * view/manage rights on the 7 rows this migration just inserted (that
     * earlier migration ran before these rows existed, so it never covered
     * them).
     */
    private function grantRights(): void
    {
        if (! Schema::hasTable('tblgroupwise_rights') || ! Schema::hasTable('tbluserprofilemaster')) {
            return;
        }

        $links = array_merge(
            array_column(self::ATTENDANCE_CHILDREN, 'link'),
            array_column(self::PAYROLL_CHILDREN, 'link')
        );
        $menuIds = DB::table('tblmenumaster')->whereIn('link', $links)->pluck('id')->all();
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
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $links = array_merge(
            array_column(self::ATTENDANCE_CHILDREN, 'link'),
            array_column(self::PAYROLL_CHILDREN, 'link')
        );

        $menuIds = DB::table('tblmenumaster')->whereIn('link', $links)->pluck('id')->all();
        if ($menuIds !== [] && Schema::hasTable('tblgroupwise_rights')) {
            DB::table('tblgroupwise_rights')->whereIn('menu_id', $menuIds)->delete();
        }

        DB::table('tblmenumaster')->whereIn('link', $links)->delete();
    }

    private function insertChildren(string $parentLink, array $children, string $icon): void
    {
        $parent = DB::table('tblmenumaster')->where('link', $parentLink)->first();
        if ($parent === null) {
            return;
        }

        foreach ($children as $child) {
            if (DB::table('tblmenumaster')->where('link', $child['link'])->exists()) {
                continue;
            }

            DB::table('tblmenumaster')->insert([
                'name' => $child['name'],
                'menu_title' => 'HRIT Management',
                'description' => $child['description'],
                'parent_menu_id' => $parent->id,
                'level' => 3,
                'status' => 1,
                'sort_order' => $child['sort_order'],
                'link' => $child['link'],
                'icon' => $icon,
                'sub_institute_id' => $parent->sub_institute_id,
                'client_id' => $parent->client_id,
                'menu_type' => 'ENTRY',
                'site_map_name' => $child['name'],
                'menu_path' => $child['name'],
                'created_at' => now(),
            ]);
        }
    }
};
