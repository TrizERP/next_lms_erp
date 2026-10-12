<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu entries for the Phase C screens, under Alumni Management (inside Institute ERP):
 *   Engagement, Mentorship, Jobs and Internships  - copy the rights of the existing "Alumni List" menu, so everyone who
 *                                                   can manage alumni sees them.
 *
 * The STUDENT page (alumni/student) has NO menu entry: the product owner decided students reach it from a link on their
 * dashboard, so no Student profile is granted anything here.
 *
 * Idempotent: rows are matched by link and a rights row is only added when missing, so it can be re-run when more
 * institutes or Student profiles are created. Forward-only down().
 */
return new class extends Migration
{
    private const STAFF_MENUS = [
        ['name' => 'Engagement', 'link' => 'alumni/engagement', 'description' => 'Engagement log, score and leaderboard'],
        ['name' => 'Mentorship', 'link' => 'alumni/mentorship', 'description' => 'Mentors, student requests, sessions and feedback'],
        ['name' => 'Jobs and Internships', 'link' => 'alumni/opportunities', 'description' => 'Opportunities offered by alumni and their approval'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster') || ! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }
        $module = DB::table('tblmenumaster')->where('name', 'Alumni Management')->where('level', 2)->first();
        $reference = DB::table('tblmenumaster')->where('link', 'alumni/list')->first();
        if ($module === null || $reference === null) {
            return;
        }

        $sort = (int) DB::table('tblmenumaster')->where('parent_menu_id', $module->id)->max('sort_order');
        $ensure = function (array $menu) use ($module, $reference, &$sort): int {
            $existing = DB::table('tblmenumaster')->where('link', $menu['link'])->first();
            if ($existing !== null) {
                return (int) $existing->id;
            }
            $sort++;

            return (int) DB::table('tblmenumaster')->insertGetId([
                'name' => $menu['name'], 'menu_title' => 'Alumni Management', 'description' => $menu['description'],
                'parent_menu_id' => $module->id, 'level' => 3, 'status' => 1, 'sort_order' => $sort, 'link' => $menu['link'],
                'icon' => $reference->icon, 'sub_institute_id' => $module->sub_institute_id, 'client_id' => $module->client_id,
                'menu_type' => 'ENTRY', 'site_map_name' => $menu['name'], 'menu_path' => $menu['name'], 'created_at' => now(),
            ]);
        };
        $grant = function (int $menuId, int $profileId, int $tenant, int $view, int $add, int $edit, int $delete): void {
            $has = DB::table('tblgroupwise_rights')->where('menu_id', $menuId)->where('profile_id', $profileId)->where('sub_institute_id', $tenant)->exists();
            if (! $has) {
                DB::table('tblgroupwise_rights')->insert([
                    'menu_id' => $menuId, 'profile_id' => $profileId, 'sub_institute_id' => $tenant,
                    'can_view' => $view, 'can_add' => $add, 'can_edit' => $edit, 'can_delete' => $delete, 'created_at' => now(),
                ]);
            }
        };

        $referenceRights = DB::table('tblgroupwise_rights')->where('menu_id', $reference->id)->get();
        foreach (self::STAFF_MENUS as $menu) {
            $id = $ensure($menu);
            foreach ($referenceRights as $r) {
                $grant($id, (int) $r->profile_id, (int) $r->sub_institute_id, (int) $r->can_view, (int) $r->can_add, (int) $r->can_edit, (int) $r->can_delete);
            }
        }
    }

    public function down(): void
    {
        // Forward-only - see class doc-block.
    }
};
