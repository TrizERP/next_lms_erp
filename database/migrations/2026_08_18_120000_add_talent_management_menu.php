<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers "Talent Management" as a new top-level (level 1) sidebar module,
 * with its 8 menus (level 2), for the as-is migration of G2G's Talent
 * Management module (Talent Dashboard, Recruitment, Onboarding, Performance
 * Reviews & Appraisals, Compensation, Mobility & Succession, Offboarding,
 * Administration) to the Next.js frontend (see app/talent-management/* and
 * app/data/routeMapper.ts's TALENT_ROUTE_NAME_MAP in the lms_k12 repo).
 *
 * Unlike HRIT Management (whose Attendance/Payroll menus reuse legacy
 * Laravel route names), every item here is a fresh Next.js-only surface with
 * no legacy screen to collide with, so all 8 links use fresh `talent.*`
 * slugs — flat, no level=3 nesting, since none of the 8 items have their own
 * children.
 *
 * sub_institute_id: same reasoning as
 * 2026_08_17_110000_add_hrit_management_menu.php — this is also a brand-new,
 * parent-less top-level module, so every currently-provisioned institute is
 * enumerated from `school_detail` at migration time rather than copied from
 * a parent row.
 *
 * Idempotent: safe to re-run: existing rows (matched by `link`) are left
 * untouched rather than duplicated.
 */
return new class extends Migration
{
    private const MODULE_LINK = 'talent_management.index';

    private const MENUS = [
        ['name' => 'Talent Dashboard', 'link' => 'talent.dashboard', 'description' => 'Talent Management: hiring, onboarding and performance overview'],
        ['name' => 'Recruitment', 'link' => 'talent.recruitment', 'description' => 'Job postings, candidates, interviews and offers'],
        ['name' => 'Onboarding', 'link' => 'talent.onboarding', 'description' => 'New-hire onboarding journeys, tasks and probation'],
        ['name' => 'Performance Reviews & Appraisals', 'link' => 'talent.performance_reviews', 'description' => 'Review cycles, goals, appraisals and calibration'],
        ['name' => 'Compensation', 'link' => 'talent.compensation', 'description' => 'Salary revisions and bonus awards'],
        ['name' => 'Mobility & Succession', 'link' => 'talent.mobility_succession', 'description' => 'Internal job postings, transfers, promotions and succession plans'],
        ['name' => 'Offboarding', 'link' => 'talent.offboarding', 'description' => 'Exit cases, clearances and exit interviews'],
        ['name' => 'Administration', 'link' => 'talent.administration', 'description' => 'Talent Management workflow configuration'],
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
                'name' => 'Talent Management',
                'menu_title' => 'Talent Management',
                'description' => 'Recruitment, onboarding, performance, mobility, succession and offboarding',
                'parent_menu_id' => 0,
                'level' => 1,
                'status' => 1,
                'sort_order' => $moduleSort + 1,
                'link' => self::MODULE_LINK,
                'icon' => 'mdi mdi-account-search-outline',
                'sub_institute_id' => $subInstituteId,
                'client_id' => $clientId,
                'menu_type' => 'ENTRY',
                'site_map_name' => 'Talent Management',
                'menu_path' => 'Talent Management',
                'created_at' => now(),
            ]);
            $module = DB::table('tblmenumaster')->where('id', $moduleId)->first();
        }

        $menuSort = 0;
        foreach (self::MENUS as $menu) {
            $menuSort++;

            if (DB::table('tblmenumaster')->where('link', $menu['link'])->exists()) {
                continue;
            }

            DB::table('tblmenumaster')->insert([
                'name' => $menu['name'],
                'menu_title' => 'Talent Management',
                'description' => $menu['description'],
                'parent_menu_id' => $module->id,
                'level' => 2,
                'status' => 1,
                'sort_order' => $menuSort,
                'link' => $menu['link'],
                'icon' => 'mdi mdi-account-search-outline',
                'sub_institute_id' => $module->sub_institute_id,
                'client_id' => $module->client_id,
                'menu_type' => 'ENTRY',
                'site_map_name' => $menu['name'],
                'menu_path' => $menu['name'],
                'created_at' => now(),
            ]);
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
