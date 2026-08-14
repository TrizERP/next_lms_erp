<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grants the "New PAL" menu item the same visibility its Homework siblings have.
 *
 * A row in tblmenumaster is NOT enough to make a menu appear. ApiLoginController
 * builds the sidebar by joining tblmenumaster against tblgroupwise_rights (by
 * profile) and tblindividual_rights (by user); a menu with no rights row is
 * invisible to everyone, including admins. The preceding migration inserted the
 * menu row only, so this one grants the rights.
 *
 * The profiles are DERIVED, not listed: whoever can already see the other items
 * under Homework gets New PAL too. That keeps the grant correct on every estate
 * (profile ids differ per deployment) and means New PAL can never be visible to
 * somebody who cannot already see the group it sits in.
 */
return new class extends Migration
{
    private const LINK = 'new_pal.index';

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster') || ! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }

        $menu = DB::table('tblmenumaster')->where('link', self::LINK)->first();
        if ($menu === null) {
            return;
        }

        // Every other live item under the same parent.
        $siblingIds = DB::table('tblmenumaster')
            ->where('parent_menu_id', $menu->parent_menu_id)
            ->where('id', '!=', $menu->id)
            ->where('status', 1)
            ->pluck('id')
            ->all();

        if ($siblingIds === []) {
            return;
        }

        $grants = DB::table('tblgroupwise_rights')
            ->select('profile_id', 'sub_institute_id')
            ->whereIn('menu_id', $siblingIds)
            ->distinct()
            ->get();

        foreach ($grants as $grant) {
            $exists = DB::table('tblgroupwise_rights')
                ->where('menu_id', $menu->id)
                ->where('profile_id', $grant->profile_id)
                ->where('sub_institute_id', $grant->sub_institute_id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('tblgroupwise_rights')->insert([
                'menu_id' => $menu->id,
                'profile_id' => $grant->profile_id,
                'sub_institute_id' => $grant->sub_institute_id,
                // New PAL is a workspace, not a CRUD screen: view is what it
                // needs. The add/edit/delete flags on the Content Model's own
                // writes are enforced by the API, not by this row.
                'can_view' => 1,
                'can_add' => 1,
                'can_edit' => 1,
                'can_delete' => 0,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblmenumaster') || ! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }

        $menuId = DB::table('tblmenumaster')->where('link', self::LINK)->value('id');
        if ($menuId !== null) {
            DB::table('tblgroupwise_rights')->where('menu_id', $menuId)->delete();
        }
    }
};
