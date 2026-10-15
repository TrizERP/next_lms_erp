<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds "Activity Stream" as a Level-3 tab right after "Virtual Classroom" in
 * Engagement → Operations.
 *
 * The row is a copy of the Virtual Classroom row under the same Level-2 parent
 * (so it inherits the same tenants, title and icon family), and its rights are
 * mirrored from that row, so the tab is visible to exactly the profiles that
 * see Virtual Classroom. The Next app already maps lmsActivityStream.index to
 * /lms/activity-stream, so no frontend change is needed.
 *
 * Only ever adds — idempotent, and a no-op if Virtual Classroom is not there.
 */
return new class extends Migration
{
    private const SIBLING_LINK = 'lmsVirtualClassroom.index';

    private const LINK = 'lmsActivityStream.index';

    private const NAME = 'Activity Stream';

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster') || ! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }

        // The Engagement → Operations copy is the one at Level 3 that is active.
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
                'icon' => 'mdi mdi-timeline-text-outline',
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

    /** Rows are shared with other modules, so rolling back would remove live menus. */
    public function down(): void
    {
    }
};
