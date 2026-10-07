<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grant rights on the Platform Services menu, so an institute administrator can
 * actually administer it.
 *
 * THE BUG THIS CLOSES
 *
 * 2026_09_11_100400 created the four `platform_services*` rows in tblmenumaster so
 * that a grant would have somewhere to live, and correctly granted nothing. Nothing
 * ever made that grant. The result was that `config/rbac_modules.php` resolves
 * `platform.workflow` / `platform.scheduler` / `platform.notification` /
 * `platform.eventbus` to a menu id perfectly well, `PermissionService::rightsFor()`
 * finds no row in tblgroupwise_rights or tblindividual_rights for that id, returns
 * null, and `allow_when_unresolved => false` denies all four actions.
 *
 * So EVERY profile was refused, the institute administrator included. The Workflow
 * and Scheduler consoles then rendered their advisory banner - "You can see these
 * workflows but not change them. Your role cannot change approval workflows for this
 * institute." - for a user whose role was never actually asked. The banner was
 * truthful about the flags and wrong about the cause: nothing had been denied, the
 * question had never been answerable.
 *
 * Verified against the live database on 2026-10-01: menu ids 679-682
 * (platform_services and its three children) have 0 rows in both rights tables.
 *
 * WHY THE PARENT ROW, NOT THE THREE CHILDREN
 *
 * `PermissionService::menuIdsFor()` tries each registered link in order and
 * `rightsFor()` walks the resolved ids until a grant is found, so a single grant on
 * the parent `platform_services` satisfies all four modules - the child rows are only
 * reached first, and missing, when the parent has none. That is the same fall-through
 * `platform.eventbus` already depends on, and `platform_services.event_bus` does not
 * exist in tblmenumaster at all, so the parent is the only id it can ever resolve to.
 *
 * Granting the parent also adds ONE navigation entry rather than four. A school that
 * wants somebody to administer Workflow without also administering Scheduler grants
 * the child row instead, which then wins because it is tried first; nothing here
 * prevents that, it only stops the base grant from being four separate decisions.
 *
 * WHY `add_fields.index` IS THE REFERENCE ROW
 *
 * Rights are copied from a menu that already carries the right, so exactly the
 * profiles that already administer institute-wide configuration get these - the same
 * technique as 2026_09_17_200002_grant_email_template_menu_rights.php, which copies
 * from the Template Master menu so the profiles that already manage templates manage
 * email layouts too.
 *
 * Field Settings is the closest existing analogue: it is the other institute-wide
 * configuration screen under Institute ERP, and it is held by profile `Admin` in 69
 * of the 74 tenants that have it. Groupwise Rights (menu 41) was rejected as the
 * reference because its holders include Teachers, LMS Teachers and a Clerk in several
 * tenants, which would have handed workflow and scheduler configuration to staff who
 * have no business holding it.
 *
 * STILL AN ADMINISTRATOR'S DECISION
 *
 * This grants the cohort that was already administering institute configuration, and
 * nothing beyond it: no teacher, no clerk, no parent. Anyone narrower than that is
 * granted in Group-wise Rights as always, and can be withdrawn from these rows the
 * same way. No code path treats this as a bypass - `perm:` still resolves through
 * PermissionService and still fails closed.
 *
 * IDEMPOTENT, keyed on menu_id: a re-run inserts nothing, so an administrator's later
 * edits are never reverted by a repeated migration.
 */
return new class extends Migration
{
    /** The row the grant is placed on. See "WHY THE PARENT ROW" above. */
    private const TARGET_LINK = 'platform_services';

    /** The row the grant is copied from. See "WHY `add_fields.index`" above. */
    private const REFERENCE_LINK = 'add_fields.index';

    public function up(): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $targetId = DB::table('tblmenumaster')->where('link', self::TARGET_LINK)->value('id');
        $referenceId = DB::table('tblmenumaster')->where('link', self::REFERENCE_LINK)->value('id');

        // No reference row means there is nothing to copy. Failing closed here is the
        // same choice PermissionService makes: no data, no grant.
        if (! $targetId || ! $referenceId) {
            return;
        }

        foreach (['tblgroupwise_rights', 'tblindividual_rights'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // Already granted, or deliberately withdrawn by an administrator. Either
            // way, leave it alone.
            if (DB::table($table)->where('menu_id', $targetId)->exists()) {
                continue;
            }

            DB::table($table)
                ->where('menu_id', $referenceId)
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($table, $targetId) {
                    $insert = [];

                    foreach ($rows as $row) {
                        $values = (array) $row;
                        unset($values['id']);
                        $values['menu_id'] = $targetId;
                        $values['created_at'] = now();
                        $insert[] = $values;
                    }

                    if ($insert !== []) {
                        DB::table($table)->insert($insert);
                    }
                });
        }
    }

    public function down(): void
    {
        $targetId = DB::table('tblmenumaster')->where('link', self::TARGET_LINK)->value('id');

        if (! $targetId) {
            return;
        }

        foreach (['tblgroupwise_rights', 'tblindividual_rights'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('menu_id', $targetId)->delete();
            }
        }
    }
};
