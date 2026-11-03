<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Organization Management → Reports tab.
 *
 * The tab was empty. This adds three Level-3 REPORT menus and lists them in it:
 *
 *   Employee Directory Report  /organization-management/reports/employee-directory
 *   Compliance Report          /organization-management/reports/compliance
 *   Disciplinary Report        /organization-management/reports/disciplinary
 *
 * Each report is a copy of the screen it reports on (same tenants, same parent menu), and its
 * rights are mirrored from that screen, so exactly the roles that can open Employee Directory,
 * Compliance Library or Diciplinary Library can open the matching report. Roles can be changed
 * afterwards on Roles & Access. The reports read the data those screens already serve, so no
 * new API or table is involved.
 *
 * Only ever adds: idempotent, and a no-op for a report whose source screen is not there.
 *
 * Run it on its own, `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_11_03_110000_add_organization_management_report_menus.php
 */
return new class extends Migration
{
    private const MODULE = 'organization-management';

    /** [report name, report link, source screen link] in the order they are shown. */
    private const REPORTS = [
        ['Employee Directory Report', '/organization-management/reports/employee-directory', '/organization-management/employee-directory'],
        ['Compliance Report', '/organization-management/reports/compliance', '/organization-management/compliance-library'],
        ['Disciplinary Report', '/organization-management/reports/disciplinary', '/organization-management/disciplinary-library'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster') || ! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        foreach (self::REPORTS as $position => [$name, $link, $sourceLink]) {
            $source = DB::table('tblmenumaster')->where('link', $sourceLink)->where('level', 3)->where('status', 1)->orderBy('id')->first();
            if ($source === null) {
                continue;
            }

            $row = DB::table('tblmenumaster')->where('link', $link)->where('level', 3)->orderBy('id')->first();

            if ($row === null) {
                $menuId = DB::table('tblmenumaster')->insertGetId([
                    'name' => $name,
                    'menu_title' => $source->menu_title,
                    'menu_sortorder' => $source->menu_sortorder,
                    'description' => $name,
                    'parent_menu_id' => $source->parent_menu_id,
                    'level' => 3,
                    'status' => 1,
                    'sort_order' => 20 + $position,
                    'link' => $link,
                    'icon' => $source->icon,
                    'sub_institute_id' => $source->sub_institute_id,
                    'client_id' => $source->client_id,
                    'menu_type' => 'REPORT',
                    'site_map_name' => $name,
                    'menu_path' => $name,
                    'created_at' => now(),
                ]);
            } else {
                $menuId = (int) $row->id;
                if ((int) $row->status !== 1) {
                    DB::table('tblmenumaster')->where('id', $menuId)->update(['status' => 1, 'updated_at' => now()]);
                }
            }

            $listed = DB::table('fees_menu_category_items')
                ->where('module_name', self::MODULE)->where('category_key', 'reports')->where('menu_id', $menuId)->exists();
            if (! $listed) {
                DB::table('fees_menu_category_items')->insert([
                    'module_name' => self::MODULE,
                    'category_key' => 'reports',
                    'menu_id' => $menuId,
                    'sort_order' => $position + 1,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (! Schema::hasTable('tblgroupwise_rights')) {
                continue;
            }
            foreach (DB::table('tblgroupwise_rights')->where('menu_id', $source->id)->get() as $grant) {
                $exists = DB::table('tblgroupwise_rights')
                    ->where('menu_id', $menuId)
                    ->where('profile_id', $grant->profile_id)
                    ->where('sub_institute_id', $grant->sub_institute_id)
                    ->exists();
                if ($exists) {
                    continue;
                }

                $copy = (array) $grant;
                unset($copy['id']);
                $copy['menu_id'] = $menuId;
                DB::table('tblgroupwise_rights')->insert($copy);
            }
        }
    }

    /** Rights rows are keyed by menu id, so removing the menus would orphan live grants. */
    public function down(): void
    {
    }
};
