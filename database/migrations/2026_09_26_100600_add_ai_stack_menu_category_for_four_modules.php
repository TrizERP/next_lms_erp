<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The AI Stack tab for Users, Parent Communication, SQAA and Library.
 *
 * WHY THESE FOUR NEED A ROW AND THE OTHER MODULES DID NOT
 *
 * A module's category tabs come from `fees_menu_categories`. That table carries rows for
 * 65 level-2 modules, and every one of them already had an `ai-stack` row — which is why
 * every other module in this work needed only to be registered and bound, not given a tab.
 *
 * These four are not among the 65. They have level-2 menus (Users id 105, Parent
 * Communication id 99, SQAA id 391, Books id 359) and they now have a complete AI Stack —
 * tools, prompts, a report layout and an example policy — with no tab to reach it from.
 * This is the row that makes it reachable.
 *
 * ONLY THE AI STACK CATEGORY
 *
 * The other eleven categories the 65 carry — Onboarding, Process Builder, Master Setup,
 * Operations, Reports, Intelligence, Communication, Workflow, Scheduler, Audit Trail, Help
 * — are not added here. Each of those is a separate feature with its own screens, and
 * creating an empty tab for eleven of them would be exactly the "tab that leads nowhere"
 * this whole piece of work exists to remove. A module gets the tab whose content exists.
 *
 * THE LEVEL-2 MENU ID IS LOOKED UP, NEVER WRITTEN DOWN
 *
 * `tblmenumaster` ids differ per estate. Each row below resolves its own menu by `link`
 * and is skipped if that menu is absent, rather than pointing a tab at whatever module
 * happens to sit at a hard-coded id.
 *
 * IDEMPOTENT, keyed on (module_name, category_key).
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_26_100600_add_ai_stack_menu_category_for_four_modules.php
 */
return new class extends Migration
{
    /**
     * module_name => [label for the description, the level-2 menu's `link`, the route].
     *
     * `module_name` is the MENU slug the category route is addressed by, and the route is
     * what `module-static-screens.tsx` maps to a screen list. The two must agree or the
     * tab renders the category's database menus instead of the AI Stack.
     *
     * @var array<string, array{0:string, 1:string, 2:string}>
     */
    private const CATEGORIES = [
        'user' => ['Users', '/user/add_user', '/modules/user/ai-stack'],
        'parent-communication' => ['Parent Communication', 'parent_communication.index', '/modules/parent-communication/ai-stack'],
        'sqaa' => ['Quality assurance', '/sqaa_master', '/modules/sqaa/ai-stack'],
        'library' => ['Library', 'javascript:void(0);', '/modules/library/ai-stack'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories') || ! Schema::hasTable('tblmenumaster')) {
            return;
        }

        foreach (self::CATEGORIES as $moduleName => [$label, $link, $route]) {
            $exists = DB::table('fees_menu_categories')
                ->where('module_name', $moduleName)
                ->where('category_key', 'ai-stack')
                ->exists();

            if ($exists) {
                continue;
            }

            $menuId = $this->menuIdFor($moduleName, $link);

            if ($menuId === null) {
                // No level-2 menu for it on this estate. A category row pointing at
                // nothing would put a tab in a navigation that cannot render it.
                continue;
            }

            DB::table('fees_menu_categories')->insert([
                'module_name' => $moduleName,
                'level2_menu_id' => $menuId,
                'category_key' => 'ai-stack',
                'label' => 'AI Stack',
                'description' => 'AI services and automation for '.$label.'.',
                'route' => $route,
                'onboarding_module_key' => null,
                'platform_module_key' => null,
                'audit_module_keys' => null,
                'sort_order' => 10,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            return;
        }

        DB::table('fees_menu_categories')
            ->whereIn('module_name', array_keys(self::CATEGORIES))
            ->where('category_key', 'ai-stack')
            ->delete();
    }

    /**
     * The level-2 menu id for one module, resolved rather than assumed.
     *
     * `Books` and `Library Report` both link to `javascript:void(0);`, which is not an
     * identifier, so the name is matched too. The level filter keeps this from picking up
     * a level-3 child with the same words in it.
     */
    private function menuIdFor(string $moduleName, string $link): ?int
    {
        $byLink = DB::table('tblmenumaster')
            ->where('level', 2)
            ->where('link', $link)
            ->where('link', '<>', 'javascript:void(0);')
            ->value('id');

        if ($byLink !== null) {
            return (int) $byLink;
        }

        // Fall back to the menu's own name. `library` is shown as "Books" in this
        // navigation, which is why the map is by module rather than by title case.
        $names = ['library' => 'Books', 'user' => 'Users', 'sqaa' => 'SQAA',
            'parent-communication' => 'Parent Communication'];

        $name = $names[$moduleName] ?? null;

        if ($name === null) {
            return null;
        }

        $byName = DB::table('tblmenumaster')
            ->where('level', 2)
            ->where(fn ($query) => $query->where('name', $name)->orWhere('menu_title', $name))
            ->value('id');

        return $byName === null ? null : (int) $byName;
    }
};
