<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu rows for AI agent enablement, so that rights can be granted against them.
 *
 * WHY THE SCREEN COULD NOT BE UNBLOCKED BY ANY ADMINISTRATOR
 *
 * The Fees AI Stack's Automations tab gates "Enable agent" on `agents.fees` create
 * rights, and told anyone who lacked them to "ask an administrator for agents.fees
 * create rights". There was no such right to ask for. `agents.fees` was not registered
 * in config/rbac_modules.php at all, so `PermissionService::check()` could not resolve it
 * to a menu row, `rightsFor()` returned null, and `allow_when_unresolved => false` denied
 * everybody — including a full administrator. The message named a remedy that did not
 * exist.
 *
 * This is the same gap the three platform services had, and it is closed the same way:
 * see 2026_09_11_100400_add_platform_services_menu_rows.php, whose reasoning applies
 * verbatim. Rights in this ERP live per menu row, so a module with no row is a module
 * nobody can be granted.
 *
 * INSERTING THESE SHOWS THEM TO NOBODY, YET.
 *
 * `MenuRightsController` builds navigation by joining tblmenumaster against the rights
 * tables, so a menu row with no rights row appears for no one. These rows are inert
 * until an administrator grants against them in Group-wise Rights — which is the point.
 * This migration grants nothing, and must not: whether a fees clerk may switch on an
 * agent that reads the school's fee records is an administrator's decision, not a
 * migration's.
 *
 * ONE ROW PER MODULE, UNDER ONE PARENT.
 *
 * The screen asks for `agents.<module>`, so the rows are per module and a school can let
 * someone enable Fees agents without also letting them enable agents everywhere else.
 * The parent exists so that a school which does not want that distinction can grant once
 * and have every module inherit — the same fallback `platform.eventbus` already uses.
 *
 * IDEMPOTENT, keyed on `link`. Tenant and client lists are copied from an existing row
 * rather than hard-coded, because they differ per environment.
 */
return new class extends Migration
{
    private const PARENT_LINK = 'ai_agents';

    /** link => [name, icon, sort order] */
    private const CHILDREN = [
        'ai_agents.fees' => ['Fees', 'mdi mdi-cash-multiple', 1],
    ];

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('tblmenumaster')) {
            return;
        }

        $root = DB::table('tblmenumaster')->where('name', 'Institute ERP')->where('level', 1)->first()
            ?? DB::table('tblmenumaster')->where('level', 1)->orderBy('sort_order')->first();

        if ($root === null) {
            // No menu tree at all — nothing to attach to, and inventing a root would put
            // a stray branch in somebody's navigation.
            return;
        }

        $parentId = DB::table('tblmenumaster')->where('link', self::PARENT_LINK)->value('id');

        if ($parentId === null) {
            $parentId = DB::table('tblmenumaster')->insertGetId([
                'name' => 'AI Agents',
                'menu_title' => 'AI Agents',
                'description' => 'Who may enable and run the AI agents in each module.',
                'parent_menu_id' => $root->id,
                'level' => 2,
                'status' => 1,
                'sort_order' => 610,
                'link' => self::PARENT_LINK,
                'icon' => 'mdi mdi-robot-outline',
                'sub_institute_id' => $root->sub_institute_id,
                'client_id' => $root->client_id,
                'menu_type' => 'ENTRY',
                'created_at' => now(),
            ]);
        }

        foreach (self::CHILDREN as $link => [$name, $icon, $sortOrder]) {
            if (DB::table('tblmenumaster')->where('link', $link)->exists()) {
                continue;
            }

            DB::table('tblmenumaster')->insert([
                'name' => $name,
                'menu_title' => $name,
                'description' => "AI agents — {$name}.",
                'parent_menu_id' => $parentId,
                'level' => 3,
                'status' => 1,
                'sort_order' => $sortOrder,
                'link' => $link,
                'icon' => $icon,
                'sub_institute_id' => $root->sub_institute_id,
                'client_id' => $root->client_id,
                'menu_type' => 'ENTRY',
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('tblmenumaster')) {
            return;
        }

        $links = array_merge([self::PARENT_LINK], array_keys(self::CHILDREN));
        $ids = DB::table('tblmenumaster')->whereIn('link', $links)->pluck('id');

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
};
