<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers "Employee Profiles", "Certifications" and "Development & Career
 * Paths" as level-2 menus NESTED UNDER the existing "Talent Management"
 * top-level module (inserted by 2026_08_18_120000_add_talent_management_menu.php)
 * - per the business requirement that this Competency Management port not
 * introduce a new top-level module.
 *
 * Mirrors 2026_08_18_120000_add_talent_management_menu.php's structure and
 * conventions exactly: same column set, same `link` idempotency guard, same
 * sub_institute_id/client_id inheritance from the parent row. Sort order
 * continues on from the existing 8 Talent Management menus rather than
 * renumbering them.
 */
return new class extends Migration
{
    private const MODULE_LINK = 'talent_management.index';

    private const MENUS = [
        ['name' => 'Employee Profiles', 'link' => 'talent.employee_profiles', 'description' => 'Competency Management: per-employee competency profiles and skill ratings'],
        ['name' => 'Certifications', 'link' => 'talent.certifications', 'description' => 'Certification & Compliance Center: credentials, requirements and compliance'],
        ['name' => 'Development & Career Paths', 'link' => 'talent.development_career_paths', 'description' => 'Development plans, action items and named career paths'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $module = DB::table('tblmenumaster')->where('link', self::MODULE_LINK)->first();
        if ($module === null) {
            // The parent Talent Management module has not been migrated yet on
            // this environment - nothing to nest under, so skip rather than
            // create a second top-level module.
            return;
        }

        $menuSort = (int) DB::table('tblmenumaster')->where('parent_menu_id', $module->id)->max('sort_order');

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

        $links = array_map(fn ($menu) => $menu['link'], self::MENUS);

        $menuIds = DB::table('tblmenumaster')->whereIn('link', $links)->pluck('id')->all();
        if ($menuIds !== [] && Schema::hasTable('tblgroupwise_rights')) {
            DB::table('tblgroupwise_rights')->whereIn('menu_id', $menuIds)->delete();
        }

        DB::table('tblmenumaster')->whereIn('link', $links)->delete();
    }
};
