<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grants the Homework Review menu (2026_09_22_160000) the rights that actually
 * make it appear.
 *
 * TWO TABLES, AND ONLY ONE OF THEM DRAWS THE TAB. This is the part that is easy
 * to get wrong, so it is written down:
 *
 *  - `tblprofilewise_menu` is a CATALOGUE — which menus a profile is allowed to
 *    be granted. The rights-administration screens read it to build their
 *    picker (see GroupwiseRightsApiController / IndividualRightsApiController).
 *    A row here alone shows nothing to anybody.
 *  - `tblgroupwise_rights` is the GRANT itself, with can_view/can_add/… on it.
 *    AbstractMenuCategoryApiController::permittedMenuIds() reads this one (plus
 *    `tblindividual_rights` for per-user overrides), and it is what decides
 *    whether a tab is drawn.
 *
 * The previous migration wrote only the catalogue row, so the menu existed,
 * sat in the right category, and was invisible. Checking against the live bar
 * confirmed the rule exactly: every Operations tab that renders has a
 * `tblgroupwise_rights` row for the caller's profile, and every one that does
 * not — Annotate Assignment, Online Exam — is missing one, however else it is
 * configured.
 *
 * Audience is inherited from Homework Submission (218) minus Student and Parent
 * profiles, for the same reason as before: that screen is role-branched and
 * legitimately reaches students, this one is staff-only behind `RequireStaff`
 * and `staff.only`, so a student grant would only render a tab that 403s.
 *
 * Idempotent on (menu_id, profile_id, sub_institute_id).
 */
return new class extends Migration
{
    private const PARENT_MENU_ID = 276;

    private const LINK = '/lms/homework/review';

    /** The menu whose audience this one inherits. */
    private const SIBLING_MENU_ID = 218;

    private const BLOCKED_PROFILE_NAMES = ['student', 'parent'];

    public function up(): void
    {
        if (! Schema::hasTable('tblgroupwise_rights') || ! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $menuId = $this->menuId();

        if ($menuId === 0) {
            return;
        }

        $blocked = Schema::hasTable('tbluserprofilemaster')
            ? DB::table('tbluserprofilemaster')
                ->whereIn(DB::raw('LOWER(TRIM(name))'), self::BLOCKED_PROFILE_NAMES)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        // Already-granted pairs, so a re-run adds nothing.
        $existing = DB::table('tblgroupwise_rights')
            ->where('menu_id', $menuId)
            ->get(['profile_id', 'sub_institute_id'])
            ->map(fn ($row) => $row->profile_id.':'.$row->sub_institute_id)
            ->flip();

        $now = now();
        $rows = [];

        foreach (DB::table('tblgroupwise_rights')->where('menu_id', self::SIBLING_MENU_ID)->get() as $grant) {
            if (in_array((int) $grant->profile_id, $blocked, true)
                || $existing->has($grant->profile_id.':'.$grant->sub_institute_id)) {
                continue;
            }

            $rows[] = [
                'menu_id' => $menuId,
                'profile_id' => $grant->profile_id,
                'sub_institute_id' => $grant->sub_institute_id,
                // Reviewing marks is an edit, not a create or a delete: a
                // teacher changes marks and remarks on a submission a student
                // made, and never adds or removes the submission itself.
                'can_view' => 1,
                'can_add' => 0,
                'can_edit' => 1,
                'can_delete' => 0,
                'dashboard_right' => $grant->dashboard_right,
                'sort_order' => $grant->sort_order,
                'is_mobile' => $grant->is_mobile,
                'created_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('tblgroupwise_rights')->insert($chunk);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tblgroupwise_rights')) {
            return;
        }

        $menuId = $this->menuId();

        if ($menuId > 0) {
            DB::table('tblgroupwise_rights')->where('menu_id', $menuId)->delete();
        }
    }

    private function menuId(): int
    {
        return (int) (DB::table('tblmenumaster')
            ->where('parent_menu_id', self::PARENT_MENU_ID)
            ->where('link', self::LINK)
            ->value('id') ?? 0);
    }
};
