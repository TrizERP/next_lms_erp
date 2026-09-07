<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fees-only category navigation feed for the Fees level-3 menu bar.
 *
 * The Fees module groups its *existing* menus into categories and renders them
 * in two steps through the existing level-3 bar:
 *
 *   FEES                [Onboarding] [Master Setup] [Transactional Data] …
 *   TRANSACTIONAL DATA  [Fees Collect] [Online Fees Collect] [Fees Circular] …
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
 * Neither endpoint is modified. This one enforces all three visibility rules in
 * SQL: the menu is status=1, the tenant is in its sub_institute_id, and the
 * caller holds a rights row for it — the same rights tables MenuRightsController
 * joins against, so a user sees precisely the Fees screens they could already
 * reach. A category with no visible menus is returned empty rather than hidden,
 * so the bar always offers the full set of categories.
 */
class FeesMenuCategoryApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $subInstituteId = (string) $request->input('sub_institute_id', '');
        $userId = (string) $request->input('user_id', '');

        if ($subInstituteId === '' || $userId === '') {
            return response()->json([
                'status' => 0,
                'message' => 'sub_institute_id and user_id are required.',
            ], 422);
        }

        if (! Schema::hasTable('fees_menu_categories') || ! Schema::hasTable('fees_menu_category_items')) {
            return response()->json(['status' => 1, 'data' => ['categories' => []]]);
        }

        $categoryRows = DB::table('fees_menu_categories')
            ->where('status', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['category_key', 'label', 'description', 'route']);

        if ($categoryRows->isEmpty()) {
            return response()->json(['status' => 1, 'data' => ['categories' => []]]);
        }

        $itemsByCategory = $this->visibleItemsByCategory(
            $subInstituteId,
            $userId,
            (string) $request->input('user_profile_name', '')
        );

        $categories = $categoryRows->map(fn ($category) => [
            'key' => (string) $category->category_key,
            'label' => (string) $category->label,
            'description' => (string) ($category->description ?? ''),
            // The category's own page. The level-3 bar links here; the page
            // itself renders the items below as its horizontal tab bar.
            'route' => (string) ($category->route ?? ''),
            'items' => $itemsByCategory[$category->category_key] ?? [],
        ])->all();

        return response()->json([
            'status' => 1,
            'data' => ['categories' => $categories],
        ]);
    }

    /**
     * Every configured menu the caller may actually see, grouped by category
     * key and ordered by the configured sort order.
     *
     * The join to tblmenumaster is what applies the visibility rules — the
     * configuration tables only say where a menu belongs, never whether it is
     * allowed to be seen.
     *
     * @return array<string,list<array{id:int,label:string,link:string}>>
     */
    private function visibleItemsByCategory(string $subInstituteId, string $userId, string $userProfileName): array
    {
        $permittedMenuIds = $this->permittedMenuIds($subInstituteId, $userId, $userProfileName);
        if ($permittedMenuIds === []) {
            return [];
        }

        $rows = DB::table('fees_menu_category_items as c')
            ->join('tblmenumaster as m', 'm.id', '=', 'c.menu_id')
            ->where('c.status', 1)
            ->where('m.status', 1)
            ->whereIn('m.id', $permittedMenuIds)
            ->whereRaw('FIND_IN_SET(?, m.sub_institute_id)', [$subInstituteId])
            ->orderBy('c.sort_order')
            ->orderBy('c.id')
            ->get(['c.category_key', 'm.id', 'm.name', 'm.link']);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->category_key][] = [
                'id' => (int) $row->id,
                'label' => (string) $row->name,
                'link' => (string) $row->link,
            ];
        }

        return $grouped;
    }

    /**
     * The menu ids this user holds a rights row for, via their profile
     * (tblgroupwise_rights) or directly (tblindividual_rights).
     *
     * This mirrors the rights join MenuRightsController performs, kept local so
     * that controller stays untouched. Students resolve through tblstudent,
     * every other profile through tbluser, exactly as it does.
     *
     * @return list<int>
     */
    private function permittedMenuIds(string $subInstituteId, string $userId, string $userProfileName): array
    {
        $isStudent = strtolower(trim($userProfileName)) === 'student';
        $userTable = $isStudent ? 'tblstudent' : 'tbluser';

        return DB::table($userTable.' as u')
            ->leftJoin('tblindividual_rights as i', function ($join) {
                $join->on('u.id', '=', 'i.user_id')
                    ->on('u.sub_institute_id', '=', 'i.sub_institute_id');
            })
            ->leftJoin('tblgroupwise_rights as g', function ($join) {
                $join->on('u.user_profile_id', '=', 'g.profile_id')
                    ->on('u.sub_institute_id', '=', 'g.sub_institute_id');
            })
            ->join('tblmenumaster as m', function ($join) {
                $join->on(function ($on) {
                    $on->on('i.menu_id', '=', 'm.id')->orOn('g.menu_id', '=', 'm.id');
                });
            })
            ->where('u.id', $userId)
            ->where('u.sub_institute_id', $subInstituteId)
            ->distinct()
            ->pluck('m.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
