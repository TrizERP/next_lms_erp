<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs the New PAL menu row and re-applies its sub-modules.
 *
 * Two things were wrong on this estate:
 *
 *  1. The row's `link` had been blanked. An empty link resolves to '#', so
 *     clicking New PAL in the level-2 panel navigated nowhere and the module
 *     was unreachable from the sidebar.
 *  2. Because 2026_08_14_140000 looked the parent up BY that link, it found
 *     nothing and completed as a silent no-op — leaving New PAL with no
 *     level-3 children, and therefore no `+N` badge beside its name while
 *     every sibling in the group had one.
 *
 * This migration restores the link and inserts the sub-module rows. It is
 * idempotent and matches on name rather than link, so it works regardless of
 * what the link currently holds.
 */
return new class extends Migration
{
    private const PARENT_LINK = 'new_pal.index';

    private const SUB_MODULES = [
        [
            'name' => 'Content Model',
            'link' => 'new_pal.content_model',
            'icon' => 'mdi mdi-cube-outline',
            'description' => 'PAL V4 four-type content model, Bloom ladder and misconception library',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $parent = DB::table('tblmenumaster')
            ->whereRaw('LOWER(name) = ?', ['new pal'])
            ->where('level', 2)
            ->orderBy('id')
            ->first();

        if ($parent === null) {
            return;
        }

        // Restore the link only when it is missing — never overwrite a link
        // somebody has deliberately pointed somewhere else.
        if (trim((string) $parent->link) === '' || $parent->link === 'javascript:void(0);') {
            DB::table('tblmenumaster')->where('id', $parent->id)->update([
                'link' => self::PARENT_LINK,
                'updated_at' => now(),
            ]);
            $parent->link = self::PARENT_LINK;
        }

        $sortOrder = (int) DB::table('tblmenumaster')
            ->where('parent_menu_id', $parent->id)
            ->max('sort_order');

        foreach (self::SUB_MODULES as $subModule) {
            if (DB::table('tblmenumaster')->where('link', $subModule['link'])->exists()) {
                continue;
            }

            $sortOrder++;

            $menuId = DB::table('tblmenumaster')->insertGetId([
                'name' => $subModule['name'],
                'menu_title' => $parent->menu_title,
                'description' => $subModule['description'],
                'parent_menu_id' => $parent->id,
                'level' => 3,
                'status' => 1,
                'sort_order' => $sortOrder,
                'link' => $subModule['link'],
                'icon' => $subModule['icon'],
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

        foreach (self::SUB_MODULES as $subModule) {
            $menuId = DB::table('tblmenumaster')->where('link', $subModule['link'])->value('id');
            if ($menuId === null) {
                continue;
            }

            if (Schema::hasTable('tblgroupwise_rights')) {
                DB::table('tblgroupwise_rights')->where('menu_id', $menuId)->delete();
            }
            DB::table('tblmenumaster')->where('id', $menuId)->delete();
        }
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
