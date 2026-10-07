<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu rows for the three platform services, so that rights can be granted
 * against them.
 *
 * WHY A MENU ROW IS REQUIRED FOR A SCREEN THAT IS NOT IN THE MENU
 * Rights in this ERP are stored per menu row: tblgroupwise_rights and
 * tblindividual_rights carry can_view / can_add / can_edit / can_delete against a
 * `menu_id`, and config/rbac_modules.php resolves an abstract module name to one
 * of those rows by `tblmenumaster.link`. A module with no resolvable row answers
 * DENY for everybody (`allow_when_unresolved => false`), which is the correct
 * failure — but it would mean the three new screens could be read and never
 * saved, by anyone, with no way for an administrator to fix it. These rows are
 * the place where the grant goes.
 *
 * INSERTING THEM SHOWS THEM TO NOBODY, YET.
 * MenuRightsController builds a user's navigation by JOINING tblmenumaster
 * against the rights tables, so a menu row with no rights row appears for no one.
 * These four rows are therefore inert until an administrator grants rights in
 * Group-wise Rights — at which point the entry appears for that profile, which is
 * exactly the behaviour every other module in this ERP has.
 *
 * IDEMPOTENT. Keyed on `link`, so a re-run inserts nothing. Links are the stable
 * identifier across environments; ids are not, which is the whole reason
 * config/rbac_modules.php resolves by link.
 *
 * TENANT AND CLIENT LISTS ARE COPIED FROM AN EXISTING ROW rather than
 * hard-coded. These columns are comma-separated lists of institute and client
 * ids that differ per environment; a literal here would be wrong on the next
 * database it met.
 *
 * Rollback: deletes only the four rows it inserted, by link. Safe, because
 * nothing else can reference them yet — and if rights HAVE been granted, the
 * rights rows are keyed by menu id and would be orphaned, which is why down()
 * removes those too.
 */
return new class extends Migration
{
    /** link => [name, icon, sort order] */
    private const CHILDREN = [
        'platform_services.notification' => ['Communication', 'mdi mdi-bell-outline', 1],
        'platform_services.scheduler' => ['Scheduler', 'mdi mdi-clock-outline', 2],
        'platform_services.workflow' => ['Workflow', 'mdi mdi-sitemap-outline', 3],
    ];

    private const PARENT_LINK = 'platform_services';

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('tblmenumaster')) {
            return;
        }

        // "Institute ERP" is the top-level area the platform-wide administration
        // already lives under (Users, Field Settings). Falling back to a root
        // node keeps the migration working on a database that has been renamed.
        $root = DB::table('tblmenumaster')->where('name', 'Institute ERP')->where('level', 1)->first()
            ?? DB::table('tblmenumaster')->where('level', 1)->orderBy('sort_order')->first();

        if ($root === null) {
            // No menu tree at all — nothing to attach to, and inventing a root
            // would put a stray branch in somebody's navigation.
            return;
        }

        $parentId = DB::table('tblmenumaster')->where('link', self::PARENT_LINK)->value('id');

        if ($parentId === null) {
            $parentId = DB::table('tblmenumaster')->insertGetId([
                'name' => 'Platform Services',
                'menu_title' => 'Platform Services',
                'description' => 'Centralised Communication, Scheduler and Workflow configuration.',
                'parent_menu_id' => $root->id,
                'level' => 2,
                'status' => 1,
                'sort_order' => 600,
                'link' => self::PARENT_LINK,
                'icon' => 'mdi mdi-cog-outline',
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
                'description' => "Platform services — {$name}.",
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

        // Grants are keyed by menu id; leaving them behind would attach rights to
        // a row that no longer exists, and the next insert would reuse the id.
        foreach (['tblgroupwise_rights', 'tblindividual_rights'] as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->whereIn('menu_id', $ids)->delete();
            }
        }

        DB::table('tblmenumaster')->whereIn('id', $ids)->delete();
    }
};
