<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers New PAL's sub-modules as level-3 menu rows.
 *
 * The level-2 panel shows a `+N` badge next to a module, where N is its number
 * of level-3 children (Sidebar.tsx). New PAL had none, so it was the only item
 * in the LMS + PAL group with no badge — not a display bug, just an empty
 * branch. This gives it the row its workspace already has.
 *
 * Clicking New PAL still opens /pal/new: handleLevel2Click navigates by the
 * item's own link whether or not it has children, so adding this row changes
 * the badge and the level-3 list without changing what the parent does.
 *
 * Rights are copied from New PAL itself rather than derived from siblings, so a
 * sub-module can never be visible to somebody who cannot see the module that
 * contains it.
 */
return new class extends Migration
{
    private const PARENT_LINK = 'new_pal.index';

    /** Sub-modules of the New PAL workspace, in the order they should appear. */
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

        $parent = $this->resolveParent();
        if ($parent === null) {
            return;
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
                // Inherited, not restated: the sub-module is scoped to exactly
                // the institutes and clients the module itself is scoped to.
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

    /**
     * The New PAL menu row.
     *
     * Matched on link first, then on name — the link is editable from the menu
     * admin screens, and a blanked link must not silently turn this migration
     * into a no-op.
     */
    private function resolveParent(): ?object
    {
        $parent = DB::table('tblmenumaster')->where('link', self::PARENT_LINK)->first();
        if ($parent !== null) {
            return $parent;
        }

        return DB::table('tblmenumaster')
            ->whereRaw('LOWER(name) = ?', ['new pal'])
            ->where('level', 2)
            ->where('status', 1)
            ->first();
    }

    /** Give the sub-module exactly the grants its parent module has. */
    private function mirrorRights(int $parentMenuId, int $menuId): void
    {
        if (! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }

        $grants = DB::table('tblgroupwise_rights')->where('menu_id', $parentMenuId)->get();

        foreach ($grants as $grant) {
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
