<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The menu rows the six new `agents.<module>` rights are stored against, plus the same
 * grants the equivalent Fees row already carries.
 *
 * WHY A ROW IS NEEDED AT ALL
 *
 * Rights in this ERP live per `tblmenumaster` row. `config/rbac_modules.php` names which
 * row a module's grant is stored on and grants nothing itself, so a key registered there
 * with no row behind it resolves to null, `allow_when_unresolved => false` denies
 * everybody — including a full administrator — and the screen tells people to ask for a
 * right that does not exist. That is precisely the bug the Fees Automations tab shipped
 * with, closed by 2026_09_18_130000 for Fees, 2026_09_19_100100 for Attendance and
 * 2026_09_21_100300 for Admission and Student, and 2026_09_22_100300 for the five before
 * these. This is the sibling for the six modules that now have an AI Stack of their own.
 *
 * WHY THIS ALSO COPIES THE GRANTS
 *
 * For the same reason its three predecessors did. An administrator has already decided,
 * for this estate, which profile may switch on a module's AI agents — whatever grants
 * `ai_agents.fees` carries are that decision. Leaving these six ungranted would reproduce
 * the original complaint six modules over, with screens that name a right nobody holds.
 *
 * It cannot widen access: a profile that cannot enable Fees agents still cannot enable any
 * of these, and if the Fees row has no grants this migration writes none. `can_delete`
 * travels with the rest rather than being assumed.
 *
 * NOTHING BELONGING TO FEES, ATTENDANCE, ADMISSION OR STUDENT IS MODIFIED. The Fees row is
 * read and never written; the other three are not touched at all.
 *
 * IDEMPOTENT, keyed on `link` and on (menu_id, profile_id, sub_institute_id). Tenant and
 * client lists are copied from the parent row rather than hard-coded, because they differ
 * per environment.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_23_100300_add_six_module_ai_agent_menu_rows.php
 */
return new class extends Migration
{
    private const PARENT_LINK = 'ai_agents';

    /** The row whose grants are mirrored — the decision this estate has already made. */
    private const TEMPLATE_LINK = 'ai_agents.fees';

    /**
     * link => [name, icon, sort order]
     *
     * The links spell the `ai_modules` key, not the menu slug, because the right is asked
     * for as `agents.<ai_modules key>`. Communication is therefore `easy_com`, which is the
     * key that module has always had, and not the slug `communication` its route carries. Sort orders continue from 4, which
     * `ai_agents.students` holds.
     */
    private const CHILDREN = [
        'ai_agents.mobile_apps' => ['Users Mobile Apps', 'mdi mdi-cellphone-cog', 10],
        'ai_agents.student_icard' => ['Student I-Card', 'mdi mdi-card-account-details-outline', 11],
        'ai_agents.certificate' => ['Certificate', 'mdi mdi-certificate-outline', 12],
        'ai_agents.easy_com' => ['Communication', 'mdi mdi-message-text-outline', 13],
        'ai_agents.timetable' => ['Time Table', 'mdi mdi-timetable', 14],
        'ai_agents.student_medical' => ['Student Medical', 'mdi mdi-medical-bag', 15],
    ];

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

        foreach (self::CHILDREN as $link => [$name, $icon, $sortOrder]) {
            $menuId = DB::table('tblmenumaster')->where('link', $link)->value('id');

            if ($menuId === null) {
                $menuId = DB::table('tblmenumaster')->insertGetId([
                    'name' => $name,
                    'menu_title' => $name,
                    'description' => "AI agents — {$name}.",
                    'parent_menu_id' => $parent->id,
                    'level' => 3,
                    'status' => 1,
                    'sort_order' => $sortOrder,
                    'link' => $link,
                    'icon' => $icon,
                    'sub_institute_id' => $parent->sub_institute_id,
                    'client_id' => $parent->client_id,
                    'menu_type' => 'ENTRY',
                    'created_at' => now(),
                ]);
            }

            $this->mirrorGrants((int) $menuId);
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('tblmenumaster')) {
            return;
        }

        // Only the six rows this migration introduced. `ai_agents` and its Fees,
        // Attendance, Admission and Student children were created by earlier migrations and
        // are left alone.
        $ids = DB::table('tblmenumaster')->whereIn('link', array_keys(self::CHILDREN))->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        // Grants are keyed by menu id; leaving them behind would attach rights to a row
        // that no longer exists, and the next insert would reuse the id.
        foreach (['tblgroupwise_rights', 'tblindividual_rights'] as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->whereIn('menu_id', $ids)->delete();
            }
        }

        DB::table('tblmenumaster')->whereIn('id', $ids)->delete();
    }

    /**
     * Copy the Fees agent row's group-wise grants onto one of the new rows.
     *
     * GROUP-WISE ONLY, ON PURPOSE. `tblindividual_rights` grants a named person an
     * exception to their profile; copying one would silently give that individual a right
     * on a module nobody decided to give them. A school that wants a named exception makes
     * it the same way it makes every other one.
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
