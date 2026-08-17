<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registers New PAL → Gamification as a level-3 sub-module of New PAL.
 *
 * The New PAL sub-nav itself is rendered by the SPA (DashboardShell's
 * `augmentPalLevel3Items`), so this row is what gives the module a menu
 * identity and menu rights — the same treatment Content Model got in
 * 2026_08_14_150000. It is matched on the parent's NAME rather than its link,
 * because that link has been blanked on at least one estate before.
 *
 * Idempotent, and deliberately narrow: it touches nothing but its own row.
 * The old PAL menu entries are not read, not moved and not modified.
 */
return new class extends Migration
{
    private const SUB_MODULE = [
        'name' => 'Gamification',
        'link' => 'new_pal.gamification',
        'icon' => 'mdi mdi-trophy-outline',
        'description' => 'PAL V4 Personal Best, badges, streaks, team challenges, Career Quest and opt-in Challenge Mode',
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

        if (DB::table('tblmenumaster')->where('link', self::SUB_MODULE['link'])->exists()) {
            return;
        }

        // Gamification sits last in New PAL, after Administration.
        $sortOrder = ((int) DB::table('tblmenumaster')
            ->where('parent_menu_id', $parent->id)
            ->max('sort_order')) + 1;

        $menuId = DB::table('tblmenumaster')->insertGetId([
            'name' => self::SUB_MODULE['name'],
            'menu_title' => $parent->menu_title,
            'description' => self::SUB_MODULE['description'],
            'parent_menu_id' => $parent->id,
            'level' => 3,
            'status' => 1,
            'sort_order' => $sortOrder,
            'link' => self::SUB_MODULE['link'],
            'icon' => self::SUB_MODULE['icon'],
            'sub_institute_id' => $parent->sub_institute_id,
            'client_id' => $parent->client_id,
            'menu_type' => 'ENTRY',
            'site_map_name' => self::SUB_MODULE['name'],
            'menu_path' => self::SUB_MODULE['name'],
            'created_at' => now(),
        ]);

        $this->mirrorRights((int) $parent->id, $menuId);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $menuId = DB::table('tblmenumaster')->where('link', self::SUB_MODULE['link'])->value('id');
        if ($menuId === null) {
            return;
        }

        if (Schema::hasTable('tblgroupwise_rights')) {
            DB::table('tblgroupwise_rights')->where('menu_id', $menuId)->delete();
        }
        DB::table('tblmenumaster')->where('id', $menuId)->delete();
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
