<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu entries for the Phase D screens, under Alumni Management (inside Institute ERP): Communication, Events, Surveys.
 * They copy the rights of the existing "Alumni List" menu, so everyone who can manage alumni sees them and nobody else
 * does (students never get these).
 *
 * Idempotent: rows are matched by link and a rights row is only added when missing. Forward-only down().
 */
return new class extends Migration
{
    private const MENUS = [
        ['name' => 'Communication', 'link' => 'alumni/communication', 'description' => 'Announcements, newsletters, templates and message history'],
        ['name' => 'Alumni Events', 'link' => 'alumni/events', 'description' => 'Reunions, talks, registrations and attendance'],
        ['name' => 'Alumni Surveys', 'link' => 'alumni/surveys', 'description' => 'Feedback surveys and results'],
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
        $referenceRights = DB::table('tblgroupwise_rights')->where('menu_id', $reference->id)->get();
        foreach (self::MENUS as $menu) {
            $id = DB::table('tblmenumaster')->where('link', $menu['link'])->value('id');
            if ($id === null) {
                $sort++;
                $id = DB::table('tblmenumaster')->insertGetId([
                    'name' => $menu['name'], 'menu_title' => 'Alumni Management', 'description' => $menu['description'],
                    'parent_menu_id' => $module->id, 'level' => 3, 'status' => 1, 'sort_order' => $sort, 'link' => $menu['link'],
                    'icon' => $reference->icon, 'sub_institute_id' => $module->sub_institute_id, 'client_id' => $module->client_id,
                    'menu_type' => 'ENTRY', 'site_map_name' => $menu['name'], 'menu_path' => $menu['name'], 'created_at' => now(),
                ]);
            }
            foreach ($referenceRights as $r) {
                $has = DB::table('tblgroupwise_rights')->where('menu_id', $id)->where('profile_id', $r->profile_id)->where('sub_institute_id', $r->sub_institute_id)->exists();
                if (! $has) {
                    DB::table('tblgroupwise_rights')->insert([
                        'menu_id' => $id, 'profile_id' => $r->profile_id, 'sub_institute_id' => $r->sub_institute_id,
                        'can_view' => $r->can_view, 'can_add' => $r->can_add, 'can_edit' => $r->can_edit, 'can_delete' => $r->can_delete, 'created_at' => now(),
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
