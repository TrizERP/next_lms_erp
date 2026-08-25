<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers "HRIT Management" as a new top-level (level 1) sidebar module,
 * with its 3 menus (level 2) and 12 submenus (level 3), for the as-is
 * migration of the HRMS Attendance/Leave/Payroll screens to the Next.js
 * frontend (see app/hrit/* pages and app/data/routeMapper.ts's
 * HRIT_ROUTE_NAME_MAP in the lms_k12 repo).
 *
 * Attendance and Payroll links reuse the LEGACY Laravel route names already
 * registered in routes/hrms.php (payroll_type.index, form16.index, etc.) —
 * those controllers/routes already exist server-side. Leave uses the modern
 * JSON API (no legacy route name), so it is keyed with invented
 * `hrit.leave.*` slugs, matching routeMapper.ts.
 *
 * sub_institute_id: unlike every other menu-insert migration in this repo,
 * this module has no existing PARENT row to copy sub_institute_id/client_id
 * from (it is a brand-new top-level module). There is also no wildcard/'all'
 * convention for that column — MenuMiddleware does a literal FIND_IN_SET
 * against it — and the repo's real precedent for new top-level visibility
 * (LmsSyncMenu, InstallOnboardingJourney) is an explicit opt-in artisan
 * command scoped per tenant, not an implicit blanket migration. Since this
 * migration still needs to run non-interactively, it enumerates every
 * currently-provisioned institute from `school_detail` at migration time so
 * the module is visible everywhere out of the box; deployments that want
 * narrower/opt-in visibility should adjust `sub_institute_id` afterwards
 * (or extend this migration into a proper sync command later, following the
 * LmsSyncMenu pattern).
 *
 * Idempotent: safe to re-run: existing rows (matched by `link`) are left
 * untouched rather than duplicated.
 */
return new class extends Migration
{
    private const MODULE_LINK = 'hrit_management.index';

    private const MENUS = [
        [
            'name' => 'Attendance Management',
            'link' => 'hrit_attendance_management.index',
            'icon' => 'mdi mdi-calendar-check-outline',
            'description' => 'HRIT: staff attendance tracking and reports',
            'children' => [
                ['name' => 'Attendance Tracking', 'link' => 'hrms_attendance.index', 'description' => 'Record and view staff attendance'],
                ['name' => 'Attendance Reports', 'link' => 'hrms_attendance_report.index', 'description' => 'Attendance summary and detail reports'],
            ],
        ],
        [
            'name' => 'Leave management',
            'link' => 'hrit_leave_management.index',
            'icon' => 'mdi mdi-calendar-remove-outline',
            'description' => 'HRIT: staff leave requests, approvals and configuration',
            'children' => [
                ['name' => 'Leave Dashboard', 'link' => 'hrit.leave.dashboard', 'description' => 'Leave balances and overview'],
                ['name' => 'Leave Requests', 'link' => 'hrit.leave.requests', 'description' => 'Apply for and approve leave requests'],
                ['name' => 'Leave Reports', 'link' => 'hrit.leave.reports', 'description' => 'Leave usage and history reports'],
                ['name' => 'Leave Configuration', 'link' => 'hrit.leave.configuration', 'description' => 'Leave types, policies and workflow settings'],
            ],
        ],
        [
            'name' => 'Payroll Management',
            'link' => 'hrit_payroll_management.index',
            'icon' => 'mdi mdi-cash-multiple',
            'description' => 'HRIT: payroll structure, deductions and payslip reports',
            'children' => [
                ['name' => 'Payroll Type', 'link' => 'payroll_type.index', 'description' => 'Configure payroll types'],
                ['name' => 'Salary Structure', 'link' => 'employee_salary_structure.index', 'description' => 'Employee salary structure setup'],
                ['name' => 'Payroll Deduction', 'link' => 'payroll_deduction.index', 'description' => 'Configure payroll deductions'],
                ['name' => 'Form 16', 'link' => 'form16.index', 'description' => 'Generate Form 16'],
                ['name' => 'Salary Certificate', 'link' => 'hrms_salary_certificate.index', 'description' => 'Generate salary certificates'],
                ['name' => 'Monthly Payroll Report', 'link' => 'monthly_payroll_report.index', 'description' => 'Monthly payroll summary report'],
            ],
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $subInstituteId = $this->resolveSubInstituteIdCsv();
        $clientId = $this->resolveClientId();

        $module = DB::table('tblmenumaster')->where('link', self::MODULE_LINK)->first();
        if ($module === null) {
            $moduleSort = (int) DB::table('tblmenumaster')->where('parent_menu_id', 0)->max('sort_order');

            $moduleId = DB::table('tblmenumaster')->insertGetId([
                'name' => 'HRIT Management',
                'menu_title' => 'HRIT Management',
                'description' => 'Staff attendance, leave and payroll management',
                'parent_menu_id' => 0,
                'level' => 1,
                'status' => 1,
                'sort_order' => $moduleSort + 1,
                'link' => self::MODULE_LINK,
                'icon' => 'mdi mdi-account-tie-outline',
                'sub_institute_id' => $subInstituteId,
                'client_id' => $clientId,
                'menu_type' => 'ENTRY',
                'site_map_name' => 'HRIT Management',
                'menu_path' => 'HRIT Management',
                'created_at' => now(),
            ]);
            $module = DB::table('tblmenumaster')->where('id', $moduleId)->first();
        }

        $menuSort = 0;
        foreach (self::MENUS as $menu) {
            $menuSort++;

            $menuRow = DB::table('tblmenumaster')->where('link', $menu['link'])->first();
            if ($menuRow === null) {
                $menuId = DB::table('tblmenumaster')->insertGetId([
                    'name' => $menu['name'],
                    'menu_title' => 'HRIT Management',
                    'description' => $menu['description'],
                    'parent_menu_id' => $module->id,
                    'level' => 2,
                    'status' => 1,
                    'sort_order' => $menuSort,
                    'link' => $menu['link'],
                    'icon' => $menu['icon'],
                    'sub_institute_id' => $module->sub_institute_id,
                    'client_id' => $module->client_id,
                    'menu_type' => 'ENTRY',
                    'site_map_name' => $menu['name'],
                    'menu_path' => $menu['name'],
                    'created_at' => now(),
                ]);
                $menuRow = DB::table('tblmenumaster')->where('id', $menuId)->first();
            }

            $subSort = 0;
            foreach ($menu['children'] as $child) {
                $subSort++;

                if (DB::table('tblmenumaster')->where('link', $child['link'])->exists()) {
                    continue;
                }

                DB::table('tblmenumaster')->insert([
                    'name' => $child['name'],
                    'menu_title' => 'HRIT Management',
                    'description' => $child['description'],
                    'parent_menu_id' => $menuRow->id,
                    'level' => 3,
                    'status' => 1,
                    'sort_order' => $subSort,
                    'link' => $child['link'],
                    'icon' => $menu['icon'],
                    'sub_institute_id' => $menuRow->sub_institute_id,
                    'client_id' => $menuRow->client_id,
                    'menu_type' => 'ENTRY',
                    'site_map_name' => $child['name'],
                    'menu_path' => $child['name'],
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

        $links = [self::MODULE_LINK];
        foreach (self::MENUS as $menu) {
            $links[] = $menu['link'];
            foreach ($menu['children'] as $child) {
                $links[] = $child['link'];
            }
        }

        $menuIds = DB::table('tblmenumaster')->whereIn('link', $links)->pluck('id')->all();
        if ($menuIds !== [] && Schema::hasTable('tblgroupwise_rights')) {
            DB::table('tblgroupwise_rights')->whereIn('menu_id', $menuIds)->delete();
        }

        DB::table('tblmenumaster')->whereIn('link', $links)->delete();
    }

    /**
     * Every currently-provisioned institute, as a CSV — see the class
     * doc-block for why this (rather than a parent copy or a wildcard) is
     * used for this specific, parent-less top-level module.
     */
    private function resolveSubInstituteIdCsv(): string
    {
        if (! Schema::hasTable('school_detail')) {
            return '1';
        }

        $ids = DB::table('school_detail')->pluck('id')->all();
        if ($ids === []) {
            return '1';
        }

        return implode(',', $ids);
    }

    /**
     * client_id is shared/global across a tenant's schools, not per-institute
     * — reuse whatever an existing top-level module already has rather than
     * inventing a value.
     */
    private function resolveClientId(): ?string
    {
        return DB::table('tblmenumaster')
            ->where('level', 1)
            ->whereNotNull('client_id')
            ->where('client_id', '!=', '')
            ->value('client_id');
    }
};
