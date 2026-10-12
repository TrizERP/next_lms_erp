<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Menu rights for the platform-level SUPER ADMIN profiles on the two Alumni screens the earlier menu migrations could not reach.
 *
 * WHY. `2026_10_13_100100` (Donations) and `2026_10_13_100300` (Audit Log) grant by profile NAME, but only to profiles that belong
 * to the Alumni Management module's institute list. The SUPER ADMIN profiles are platform-level (`sub_institute_id = 0`), so they
 * were skipped and hold 14 of the 16 alumni screens. Access itself is not affected - the controllers accept the Super Admin role -
 * this only makes the two menu entries visible to them.
 *
 * WHAT. For every active profile named SUPER ADMIN (any institute id), one rights row per screen if it does not exist yet:
 *     alumni/donations   view + add + edit, NO delete (nothing about a donation is ever deleted)
 *     alumni/audit       view only (the log is read-only)
 * With the 6 platform-level profiles that exist today this is exactly 12 rows. The rights row carries the profile's own
 * sub_institute_id, the same way their existing alumni rights are stored.
 *
 * Idempotent (a row is added only when missing), touches no existing row, forward-only down(). NOT APPLIED to production: it
 * needs explicit approval. The exact rows, a before/after report and the reversal SQL are in docs/ALUMNI_PORTAL_RELEASE_RUNBOOK.md.
 */
return new class extends Migration
{
    /** link => [view, add, edit, delete] */
    private const GRANTS = [
        'alumni/donations' => [1, 1, 1, 0],
        'alumni/audit' => [1, 0, 0, 0],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster') || ! Schema::hasTable('tblgroupwise_rights') || ! Schema::hasTable('tbluserprofilemaster')) {
            return;
        }
        $profiles = DB::table('tbluserprofilemaster')->where('status', 1)->whereRaw("UPPER(TRIM(name)) = 'SUPER ADMIN'")->get(['id', 'sub_institute_id']);
        foreach (self::GRANTS as $link => [$view, $add, $edit, $delete]) {
            $menuId = DB::table('tblmenumaster')->where('link', $link)->value('id');
            if ($menuId === null) {
                continue;   // the screen's own menu migration has not run: nothing to grant
            }
            foreach ($profiles as $p) {
                $exists = DB::table('tblgroupwise_rights')->where('menu_id', $menuId)->where('profile_id', $p->id)->where('sub_institute_id', $p->sub_institute_id)->exists();
                if (! $exists) {
                    DB::table('tblgroupwise_rights')->insert([
                        'menu_id' => $menuId, 'profile_id' => $p->id, 'sub_institute_id' => $p->sub_institute_id,
                        'can_view' => $view, 'can_add' => $add, 'can_edit' => $edit, 'can_delete' => $delete, 'created_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Forward-only. To reverse by hand, delete the rows listed in the runbook (matched by menu_id + profile_id).
    }
};
