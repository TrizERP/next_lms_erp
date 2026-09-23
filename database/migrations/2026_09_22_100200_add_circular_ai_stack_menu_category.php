<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives the Circular module the AI Stack category row every other module already has.
 *
 * WHY CIRCULAR HAS NONE AND THE OTHER FOUR DO
 *
 * 2026_09_17_100001 rolled the level-3 category bar out to every active level-2 menu that
 * has at least one active level-3 child - a menu with no children is a screen rather than
 * a module, and it was skipped. Exam (67), Hostel (121), PTM (258) and Student Request
 * (264) each have children and were seeded with all ten categories. Circular (102) is a
 * level-2 menu that is itself a screen: its own `link` is `circular.index` and it has no
 * children, so it got none.
 *
 * `ModuleCategoryPage` looks a category up by `(module_name, category_key)` and renders
 * the static screens registered for it, so without this row `/modules/circular/ai-stack`
 * has a heading with no label and the tabs sit under "This category is not configured".
 *
 * ONLY THE AI STACK CATEGORY, NOT ALL TEN
 *
 * The other nine categories group menus, and Circular has no menus to group - seeding
 * them would add nine permanently empty tabs to a module that does not have a category
 * bar at all. This row exists so one route resolves to one label; it changes nothing about
 * how the Circular menu itself behaves, and the level-2 menu still opens the Circular
 * screen it always did.
 *
 * `module_name` is `circular`, which is what `slug()` in 2026_09_17_100001 would have
 * derived from the menu's own name and what the Next app registers its static screens
 * under. `sort_order` 10 matches the AI Stack category everywhere else.
 *
 * IDEMPOTENT, keyed on (module_name, category_key). NOTHING BELONGING TO ANOTHER MODULE
 * IS TOUCHED.
 *
 * Run it on its own - `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_22_100200_add_circular_ai_stack_menu_category.php
 */
return new class extends Migration
{
    private const MODULE_NAME = 'circular';

    private const CATEGORY_KEY = 'ai-stack';

    /** The Circular level-2 menu. Resolved by link, never by a hardcoded id. */
    private const MENU_LINK = 'circular.index';

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories')
            || ! Schema::hasColumn('fees_menu_categories', 'level2_menu_id')) {
            return;
        }

        $exists = DB::table('fees_menu_categories')
            ->where('module_name', self::MODULE_NAME)
            ->where('category_key', self::CATEGORY_KEY)
            ->exists();

        if ($exists) {
            return;
        }

        // Menu ids differ between environments, so the row is found by its stable link. A
        // null id is acceptable here: the category page reads the row by module and key,
        // and the id is only the back-reference to the menu it belongs to.
        $menuId = DB::table('tblmenumaster')
            ->where('link', self::MENU_LINK)
            ->where('level', 2)
            ->value('id');

        DB::table('fees_menu_categories')->insert([
            'module_name' => self::MODULE_NAME,
            'level2_menu_id' => $menuId,
            'category_key' => self::CATEGORY_KEY,
            'label' => 'AI Stack',
            'description' => 'AI services and automation for Circular.',
            'route' => '/modules/'.self::MODULE_NAME.'/'.self::CATEGORY_KEY,
            'sort_order' => 10,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            return;
        }

        // Only the one row this migration created.
        DB::table('fees_menu_categories')
            ->where('module_name', self::MODULE_NAME)
            ->where('category_key', self::CATEGORY_KEY)
            ->delete();
    }
};
