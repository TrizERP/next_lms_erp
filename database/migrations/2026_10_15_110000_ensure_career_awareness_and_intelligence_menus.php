<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes "career awareness" and "career intelligence" appear in the Career
 * Counseling Level-2 flyout wherever "career explorer" already does.
 *
 * Both rows were created by hand in one database, so another environment can
 * have the Level-2 flyout showing only "career explorer": the row is missing,
 * or it exists with no tblgroupwise_rights for the profiles that see its
 * sibling. This migration (1) creates whichever row is missing under the same
 * Level-1 parent as the "/career-explorer" row, and (2) copies that row's
 * rights onto both. It only ever adds — existing rows and grants are left
 * untouched — so it is idempotent and safe to re-run.
 *
 * No-ops harmlessly if there is no "/career-explorer" Level-2 row.
 */
return new class extends Migration
{
    private const SIBLING_LINK = '/career-explorer';

    private const TARGETS = [
        ['name' => 'career awareness', 'link' => '/career-awareness', 'sort_order' => 1],
        ['name' => 'career intelligence', 'link' => '/career-intelligence', 'sort_order' => 4],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster') || ! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }

        $sibling = DB::table('tblmenumaster')
            ->where('link', self::SIBLING_LINK)
            ->where('level', 2)
            ->orderBy('id')
            ->first();

        if ($sibling === null) {
            return;
        }

        foreach (self::TARGETS as $target) {
            $row = DB::table('tblmenumaster')
                ->where('parent_menu_id', $sibling->parent_menu_id)
                ->where('level', 2)
                ->where('link', $target['link'])
                ->orderBy('id')
                ->first();

            if ($row === null) {
                $menuId = DB::table('tblmenumaster')->insertGetId([
                    'name' => $target['name'],
                    'menu_title' => $sibling->menu_title,
                    'description' => $target['name'],
                    'parent_menu_id' => $sibling->parent_menu_id,
                    'level' => 2,
                    'status' => 1,
                    'sort_order' => $target['sort_order'],
                    'link' => $target['link'],
                    'icon' => $sibling->icon,
                    'sub_institute_id' => $sibling->sub_institute_id,
                    'client_id' => $sibling->client_id,
                    'site_map_name' => $target['name'],
                    'menu_path' => $target['name'],
                    'created_at' => now(),
                ]);
            } else {
                $menuId = (int) $row->id;
                if ((int) $row->status !== 1) {
                    DB::table('tblmenumaster')->where('id', $menuId)->update([
                        'status' => 1,
                        'updated_at' => now(),
                    ]);
                }
            }

            $this->mirrorRights((int) $sibling->id, $menuId);
        }
    }

    /** Rows are shared with other modules, so rolling back would remove live menus. */
    public function down(): void
    {
    }

    /** A menu is visible to exactly the profiles its sibling is. */
    private function mirrorRights(int $fromMenuId, int $toMenuId): void
    {
        foreach (DB::table('tblgroupwise_rights')->where('menu_id', $fromMenuId)->get() as $grant) {
            $exists = DB::table('tblgroupwise_rights')
                ->where('menu_id', $toMenuId)
                ->where('profile_id', $grant->profile_id)
                ->where('sub_institute_id', $grant->sub_institute_id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('tblgroupwise_rights')->insert([
                'menu_id' => $toMenuId,
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
