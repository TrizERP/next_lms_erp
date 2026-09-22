<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The menu row `agents.attendance` rights are stored against, plus the same grants the
 * equivalent Fees row already carries.
 *
 * WHY A ROW IS NEEDED AT ALL
 *
 * Rights in this ERP live per `tblmenumaster` row. `config/rbac_modules.php` names which
 * row a module's grant is stored on and grants nothing itself, so a key registered there
 * with no row behind it resolves to null, `allow_when_unresolved => false` denies
 * everybody — including a full administrator — and the screen tells people to ask for a
 * right that does not exist. That is precisely the bug the Fees Automations tab shipped
 * with, and 2026_09_18_130000_add_ai_agents_menu_rows_for_rights.php closed it by adding
 * `ai_agents` and `ai_agents.fees`. This adds the attendance sibling under the same
 * parent, which already exists.
 *
 * WHY THIS ONE ALSO COPIES THE GRANTS, WHERE THE FEES ONE DID NOT
 *
 * The Fees migration deliberately granted nobody anything and left it to an administrator.
 * That is the right default for a new capability nobody has decided about yet — but it is
 * not the situation here. An administrator has already decided, for this estate, which
 * profile may switch on a module's AI agents: profile 1 holds view/add/edit on
 * `ai_agents.fees`. Leaving attendance ungranted would reproduce the original complaint
 * one module over, with a screen that names a right nobody holds.
 *
 * So this copies whatever grants `ai_agents.fees` currently carries onto the attendance
 * row, per profile and per institute, and copies nothing else. It cannot widen access: a
 * profile that cannot enable Fees agents still cannot enable Attendance ones, and if the
 * Fees row has no grants this migration writes none. `can_delete` travels with the rest
 * rather than being assumed.
 *
 * IDEMPOTENT, keyed on `link` and on (menu_id, profile_id, sub_institute_id). Tenant and
 * client lists are copied from the parent row rather than hard-coded, because they differ
 * per environment.
 */
return new class extends Migration
{
    private const PARENT_LINK = 'ai_agents';

    private const LINK = 'ai_agents.attendance';

    /** The row whose grants are mirrored — the decision this estate has already made. */
    private const TEMPLATE_LINK = 'ai_agents.fees';

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('tblmenumaster')) {
            return;
        }

        $parent = DB::table('tblmenumaster')->where('link', self::PARENT_LINK)->first();

        if ($parent === null) {
            // The parent is created by the migration that added the Fees row. If it is
            // absent this estate has not run that one, and inventing a second parent here
            // would put a stray branch in somebody's navigation.
            return;
        }

        $menuId = DB::table('tblmenumaster')->where('link', self::LINK)->value('id');

        if ($menuId === null) {
            $menuId = DB::table('tblmenumaster')->insertGetId([
                'name' => 'Attendance',
                'menu_title' => 'Attendance',
                'description' => 'AI agents — Attendance.',
                'parent_menu_id' => $parent->id,
                'level' => 3,
                'status' => 1,
                'sort_order' => 2,
                'link' => self::LINK,
                'icon' => 'mdi mdi-calendar-check',
                'sub_institute_id' => $parent->sub_institute_id,
                'client_id' => $parent->client_id,
                'menu_type' => 'ENTRY',
                'created_at' => now(),
            ]);
        }

        $this->mirrorGrants((int) $menuId);
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('tblmenumaster')) {
            return;
        }

        $menuId = DB::table('tblmenumaster')->where('link', self::LINK)->value('id');

        if ($menuId === null) {
            return;
        }

        // Grants are keyed by menu id; leaving them behind would attach rights to a row
        // that no longer exists, and the next insert would reuse the id.
        foreach (['tblgroupwise_rights', 'tblindividual_rights'] as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->where('menu_id', $menuId)->delete();
            }
        }


        DB::table('tblmenumaster')->where('id', $menuId)->delete();
    }

    /**
     * Copy the Fees agent row's group-wise grants onto the attendance row.
     *
     * GROUP-WISE ONLY, ON PURPOSE. `tblindividual_rights` grants a named person an
     * exception to their profile; copying one would silently give that individual a right
     * on a module nobody decided to give them, and the estate holds no individual grant on
     * the Fees agent row to copy in any case. A school that wants a named exception for
     * attendance makes it the same way it makes every other one.
     */
    private function mirrorGrants(int $menuId): void
    {
        if (! DB::getSchemaBuilder()->hasTable('tblgroupwise_rights')) {
            return;
        }

        $templateId = DB::table('tblmenumaster')->where('link', self::TEMPLATE_LINK)->value('id');

        if ($templateId === null) {
            return;
        }

        foreach (DB::table('tblgroupwise_rights')->where('menu_id', $templateId)->get() as $source) {
            $profileId = $source->profile_id ?? null;

            if ($profileId === null) {
                continue;
            }

            $institute = $source->sub_institute_id ?? null;

            $exists = DB::table('tblgroupwise_rights')
                ->where('menu_id', $menuId)
                ->where('profile_id', $profileId)
                ->where(fn ($query) => $institute === null
                    ? $query->whereNull('sub_institute_id')
                    : $query->where('sub_institute_id', $institute))
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('tblgroupwise_rights')->insert([
                'menu_id' => $menuId,
                'profile_id' => $profileId,
                'can_view' => $source->can_view ?? 0,
                'can_add' => $source->can_add ?? 0,
                'can_edit' => $source->can_edit ?? 0,
                'can_delete' => $source->can_delete ?? 0,
                'sub_institute_id' => $institute,
                'created_at' => now(),
            ]);
        }
    }
};
