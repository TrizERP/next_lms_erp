<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives the 4 Career Awareness tabs — Career Certainty, Career Ambition,
 * Career Alignment, Career Originality — real tblmenumaster rows. These were
 * previously client-side-only tab state; they are now real Next.js routes
 * (/career-awareness/{certainty,ambition,alignment,originality}) backed by
 * the studentAspiration/studentAmbition/careerAlignment/studentOriginality
 * endpoints, so Access Roles needs real rows to grant/revoke `can_view` on.
 *
 * Idempotent (checks by name under the parent before inserting), and
 * no-ops harmlessly if no "Career Awareness" level-2 menu row exists yet in
 * a given environment.
 */
return new class extends Migration
{
    private const SUB_MODULES = [
        ['name' => 'Career Certainty', 'link' => 'career_awareness.certainty', 'sort_order' => 1],
        ['name' => 'Career Ambition', 'link' => 'career_awareness.ambition', 'sort_order' => 2],
        ['name' => 'Career Alignment', 'link' => 'career_awareness.alignment', 'sort_order' => 3],
        ['name' => 'Career Originality', 'link' => 'career_awareness.originality', 'sort_order' => 4],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $parent = DB::table('tblmenumaster')
            ->whereRaw('LOWER(name) = ?', ['career awareness'])
            ->where('level', 2)
            ->orderBy('id')
            ->first();

        if ($parent === null) {
            return;
        }

        foreach (self::SUB_MODULES as $subModule) {
            $existing = DB::table('tblmenumaster')
                ->where('parent_menu_id', $parent->id)
                ->whereRaw('LOWER(name) = ?', [strtolower($subModule['name'])])
                ->first();

            if ($existing !== null) {
                DB::table('tblmenumaster')->where('id', $existing->id)->update([
                    'sort_order' => $subModule['sort_order'],
                    'updated_at' => now(),
                ]);
                continue;
            }

            $menuId = DB::table('tblmenumaster')->insertGetId([
                'name' => $subModule['name'],
                'menu_title' => $parent->menu_title,
                'description' => $subModule['name'],
                'parent_menu_id' => $parent->id,
                'level' => 3,
                'status' => 1,
                'sort_order' => $subModule['sort_order'],
                'link' => $subModule['link'],
                'icon' => $parent->icon,
                'sub_institute_id' => $parent->sub_institute_id,
                'client_id' => $parent->client_id,
                'menu_type' => 'ENTRY',
                'site_map_name' => $subModule['name'],
                'menu_path' => $subModule['name'],
                'created_at' => now(),
            ]);

            $this->mirrorRights((int) $parent->id, $menuId);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $links = array_column(self::SUB_MODULES, 'link');

        $menuIds = DB::table('tblmenumaster')->whereIn('link', $links)->pluck('id');

        if ($menuIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('tblgroupwise_rights')) {
            DB::table('tblgroupwise_rights')->whereIn('menu_id', $menuIds)->delete();
        }
        DB::table('tblmenumaster')->whereIn('id', $menuIds)->delete();
    }

    /** A sub-module is visible to exactly the profiles its module is. */
    private function mirrorRights(int $parentMenuId, int $menuId): void
    {
        if (! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }

        foreach (DB::table('tblgroupwise_rights')->where('menu_id', $parentMenuId)->get() as $grant) {
            $exists = DB::table('tblgroupwise_rights')
                ->where('menu_id', $menuId)
                ->where('profile_id', $grant->profile_id)
                ->where('sub_institute_id', $grant->sub_institute_id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('tblgroupwise_rights')->insert([
                'menu_id' => $menuId,
                'profile_id' => $grant->profile_id,
                'sub_institute_id' => $grant->sub_institute_id,
                'can_view' => $grant->can_view,
                'can_add' => $grant->can_add,
                'can_edit' => $grant->can_edit,
                'can_delete' => $grant->can_delete,
                'created_at' => now(),
            ]);
        }
    }
};
