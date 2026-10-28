<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds "Mentor Skill Mapping" as a Level-3 tab after "Capability Explorer" in
 * People & Competency → Capability Intelligence.
 *
 * It is the HR screen that maps staff skills to O*NET elements, which is what
 * lets Career Counseling's "Suggested mentors" card rank staff. The row is a
 * copy of the Capability Explorer row (same tenants, title, type), and its
 * rights are mirrored from that row, so it is visible to exactly the profiles
 * that see Capability Explorer. Who may actually save a mapping is decided by
 * CareerMentorController (administrator, hr_manager, hr_executive), not here.
 *
 * Only ever adds: idempotent, and a no-op if Capability Explorer is not there.
 *
 * Run it on its own, `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_10_27_110000_add_mentor_skill_mapping_menu.php
 */
return new class extends Migration
{
    private const SIBLING_LINK = '/capability-intelligence/capability-explorer';

    private const LINK = '/capability-intelligence/mentor-skill-mapping';

    private const NAME = 'Mentor Skill Mapping';

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster') || ! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }

        $sibling = DB::table('tblmenumaster')
            ->where('link', self::SIBLING_LINK)
            ->where('level', 3)
            ->where('status', 1)
            ->orderBy('id')
            ->first();

        if ($sibling === null) {
            return;
        }

        $row = DB::table('tblmenumaster')
            ->where('parent_menu_id', $sibling->parent_menu_id)
            ->where('level', 3)
            ->where('link', self::LINK)
            ->orderBy('id')
            ->first();

        if ($row === null) {
            $menuId = DB::table('tblmenumaster')->insertGetId([
                'name' => self::NAME,
                'menu_title' => $sibling->menu_title,
                'menu_sortorder' => $sibling->menu_sortorder,
                'description' => self::NAME,
                'parent_menu_id' => $sibling->parent_menu_id,
                'level' => 3,
                'status' => 1,
                'sort_order' => ((int) $sibling->sort_order) + 1,
                'link' => self::LINK,
                'icon' => $sibling->icon,
                'sub_institute_id' => $sibling->sub_institute_id,
                'client_id' => $sibling->client_id,
                'menu_type' => $sibling->menu_type,
                'site_map_name' => self::NAME,
                'menu_path' => self::NAME,
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

        foreach (DB::table('tblgroupwise_rights')->where('menu_id', $sibling->id)->get() as $grant) {
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

    /** Rights rows are keyed by menu id, so removing the menu would orphan live grants. */
    public function down(): void
    {
    }
};
