<?php

namespace App\Http\Controllers\api;

/**
 * Teach/Learn's category navigation feed for the Teach/Learn level-3 menu
 * bar — the same pattern as FeesMenuCategoryApiController, over the same
 * `fees_menu_categories` / `fees_menu_category_items` tables, scoped to
 * `module_name = 'teach_learn'` (see
 * 2026_09_10_150001_seed_teach_learn_menu_categories.php for the seeded
 * categories and items, and AbstractMenuCategoryApiController for the shared
 * query and visibility-rule logic).
 */
class TeachLearnMenuCategoryApiController extends AbstractMenuCategoryApiController
{
    protected function moduleName(): string
    {
        return 'teach_learn';
    }
}
