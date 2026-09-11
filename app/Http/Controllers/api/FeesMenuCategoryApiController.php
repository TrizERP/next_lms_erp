<?php

namespace App\Http\Controllers\api;

/**
 * Fees' category navigation feed for the Fees level-3 menu bar.
 *
 * The Fees module groups its *existing* menus into categories and renders them
 * in two steps through the existing level-3 bar:
 *
 *   FEES                [Onboarding] [Master Setup] [Operations] [Reports] …
 *   OPERATIONS          [Fees Collect] [Online Fees Collect] [Fees Circular] …
 *
 * The categories and their membership are data, not code: they live in
 * `fees_menu_categories` and `fees_menu_category_items` (see
 * 2026_09_05_110000_create_fees_menu_category_tables.php), so the grouping is
 * changed by editing rows. Nothing here is hardcoded.
 *
 * They are deliberately not tblmenumaster rows — that table is the 3-level menu
 * tree, and putting the categories in it would either displace the real Fees
 * screens from level 3 or push them to an unsupported 4th level. Keeping them
 * separate means no menu row changes level, parent, link, status or rights, and
 * no other module is affected.
 *
 * Why not reuse an existing endpoint:
 *
 *  - /api/menu-rights drops everything Master Setup needs: those rows are
 *    menu_type='MASTER', which every level of that query filters out. It also
 *    drops "Fees Prediction", because buildMenuTree() can only attach a level-3
 *    row to a level-2 parent that survived, and its parent "(AI) Artificial
 *    Intelligence" is status=0.
 *  - /api/master-menu-rights returns the Fees masters but mixes in unrelated
 *    entries (Student Quota, Add Student, Email, SMS API, Field Settings, …)
 *    and carries no `status` column, so the "only status=1 menus are visible"
 *    rule cannot be enforced from its response.
 *
 * Neither endpoint is modified. All three visibility rules (menu status = 1,
 * tenant provisioning, caller's menu rights) are enforced in SQL by the shared
 * base class — see AbstractMenuCategoryApiController. This class only names
 * the module: `fees_menu_categories`/`fees_menu_category_items` are shared
 * with Teach/Learn (see TeachLearnMenuCategoryApiController) via the
 * `module_name` column, so every query is scoped to 'fees' and the two
 * modules' rows can never collide or leak into each other.
 */
class FeesMenuCategoryApiController extends AbstractMenuCategoryApiController
{
    protected function moduleName(): string
    {
        return 'fees';
    }
}
