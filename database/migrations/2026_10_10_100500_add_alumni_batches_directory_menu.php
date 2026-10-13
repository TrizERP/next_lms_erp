<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Alumni Directory and Batches under Alumni Management (inside Institute ERP) and gives them the SAME
 * rights as the existing "Alumni List" menu: every profile/institute that can see the list can see these.
 *
 * Idempotent: rows are matched by link, and a rights row is only added when it is missing, so it is safe to
 * re-run (for example after more institutes are provisioned). Requires 2026_10_10_100100..100400 first; it
 * does nothing if the Alumni Management module or the Alumni List menu is not there.
 *
 * Forward-only down(): removing menus would also strip rights other people may rely on.
 */
return new class extends Migration
{
    private const MENUS = [
        ['name' => 'Alumni Directory', 'link' => 'alumni/directory', 'description' => 'Searchable alumni network (privacy applied)'],
        ['name' => 'Batches', 'link' => 'alumni/batches', 'description' => 'Batch, passing-year and course statistics'],
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
        foreach (self::MENUS as $menu) {
            $existing = DB::table('tblmenumaster')->where('link', $menu['link'])->first();
            if ($existing === null) {
                $sort++;
                $id = DB::table('tblmenumaster')->insertGetId([
                    'name' => $menu['name'], 'menu_title' => 'Alumni Management', 'description' => $menu['description'],
                    'parent_menu_id' => $module->id, 'level' => 3, 'status' => 1, 'sort_order' => $sort,
                    'link' => $menu['link'], 'icon' => $reference->icon, 'sub_institute_id' => $module->sub_institute_id,
                    'client_id' => $module->client_id, 'menu_type' => 'ENTRY', 'site_map_name' => $menu['name'],
                    'menu_path' => $menu['name'], 'created_at' => now(),
                ]);
            } else {
                $id = $existing->id;
            }

            foreach (DB::table('tblgroupwise_rights')->where('menu_id', $reference->id)->get() as $r) {
                $has = DB::table('tblgroupwise_rights')->where('menu_id', $id)
                    ->where('profile_id', $r->profile_id)->where('sub_institute_id', $r->sub_institute_id)->exists();
                if (! $has) {
                    DB::table('tblgroupwise_rights')->insert([
                        'menu_id' => $id, 'profile_id' => $r->profile_id, 'sub_institute_id' => $r->sub_institute_id,
                        'can_view' => $r->can_view, 'can_add' => $r->can_add, 'can_edit' => $r->can_edit,
                        'can_delete' => $r->can_delete, 'created_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Forward-only - see class doc-block.
    }
};
