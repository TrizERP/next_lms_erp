<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renames the AI & Intelligence submodule "Prompt Management" to "Template Management".
 *
 * WHY A RENAME RATHER THAN A NEW MENU ROW
 *
 * `2026_09_10_000001_add_ai_intelligence_menu` already created this row, as one of
 * twelve, and granted it to every active profile on the estate — roughly 7,605
 * `tblgroupwise_rights` rows. Adding a second row called "Template Management" would
 * leave two entries in the sidebar for one screen, only one of which anybody can see,
 * and would need the whole grant repeated. Renaming the row keeps every right that
 * already exists and leaves the sidebar with one honest entry.
 *
 * WHY THE LINK DOES NOT CHANGE
 *
 * `link` stays `ai_intelligence.prompts`. It is matched by `AI_INTELLIGENCE_ROUTES` in
 * the SPA's `routeMapper`, which builds its keys from the capability registry's slugs —
 * so changing it here means changing the slug there, which changes the URL
 * `/ai/prompts`, which is already bookmarked and already written into this migration's
 * predecessor. The label is what an administrator reads; the slug is an identifier.
 * Renaming the first and leaving the second is the change with no blast radius.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_14_000002_rename_prompt_management_to_template_management.php
 */
return new class extends Migration
{
    private const LINK = 'ai_intelligence.prompts';

    private const MODULE_NAME = 'AI & Intelligence';

    private const NEW_NAME = 'Template Management';

    private const OLD_NAME = 'Prompt Management';

    public function up(): void
    {
        $this->rename(self::NEW_NAME);
    }

    public function down(): void
    {
        $this->rename(self::OLD_NAME);
    }

    private function rename(string $name): void
    {
        if (! Schema::hasTable('tblmenumaster')) {
            return;
        }

        $columns = [
            'name' => $name,
            // `description`, `site_map_name` and `menu_path` all carry the label on
            // this estate — the sidebar, the search and the breadcrumb read different
            // ones. Updating only `name` renames the entry in one place and leaves the
            // breadcrumb reading "AI & Intelligence / Prompt Management".
            'description' => $name,
            'site_map_name' => $name,
            'menu_path' => self::MODULE_NAME . ' / ' . $name,
            'updated_at' => now(),
        ];

        // Only the columns this estate actually has. `tblmenumaster` differs between
        // installs and an update naming a missing column fails the whole migration.
        $present = array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn('tblmenumaster', $column),
            ARRAY_FILTER_USE_KEY
        );

        DB::table('tblmenumaster')->where('link', self::LINK)->update($present);
    }
};
