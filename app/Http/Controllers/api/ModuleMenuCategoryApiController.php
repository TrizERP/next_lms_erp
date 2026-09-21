<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The category navigation feed for EVERY module, not one module per class.
 *
 * ── WHY THIS EXISTS ─────────────────────────────────────────────────────────
 *
 * `fees_menu_categories` / `fees_menu_category_items` already hold the category
 * navigation for 64 modules — Onboarding, Process Builder, Master Setup,
 * Operations, Reports, Intelligence, Help Guide/Support, Communication, AI
 * Stack — each row carrying its own `/modules/<module_name>/<category>` route
 * and the `level2_menu_id` of the tblmenumaster row the module IS.
 *
 * Only two of those 64 could be reached: FeesMenuCategoryApiController and
 * TeachLearnMenuCategoryApiController name their module in PHP, so every other
 * module's rows were unreadable and its navigation fell back to the flat
 * level-3 menu list. This controller takes the module from the request instead,
 * so the same rows serve every module without a class per module.
 *
 * Neither existing controller is touched: `/api/fees/menu-categories` and
 * `/api/teach-learn/menu-categories` keep their own routes, their own
 * responses and their own callers.
 *
 * ── HOW A CALLER NAMES THE MODULE ───────────────────────────────────────────
 *
 * Either by slug (`module_name=student`) or by the level-2 menu row the user
 * actually clicked (`level2_menu_id=259`). The second form is what the LMS
 * shell uses: it knows which tblmenumaster row is selected and nothing else,
 * and asking it to derive a slug from a label would be exactly the hardcoded
 * mapping these rows exist to avoid.
 *
 * Every response also carries `modules` — the full slug ↔ level-2 directory —
 * so the shell can attach each module's canonical Intelligence route to the
 * menu it builds without a second request. It is 64 rows of navigation
 * metadata, identical for every tenant; the per-module ITEMS are what carry
 * rights, and those go through the shared query in the parent class.
 */
class ModuleMenuCategoryApiController extends AbstractMenuCategoryApiController
{
    /** Resolved per request; see moduleName(). */
    private string $module = '';

    protected function moduleName(): string
    {
        return $this->module;
    }

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
            return response()->json([
                'status' => 1,
                'data' => ['modules' => [], 'module' => null, 'categories' => []],
            ]);
        }

        $directory = $this->directory();
        $module = $this->resolveModule($request, $directory);

        if ($module === null) {
            // Not an error: the shell asks for the directory alone on start-up,
            // and a module with no configured categories is a module that keeps
            // its existing flat menu rather than one that failed.
            return response()->json([
                'status' => 1,
                'data' => ['modules' => $directory, 'module' => null, 'categories' => []],
            ]);
        }

        $this->module = $module['module_name'];

        $categoryRows = DB::table('fees_menu_categories')
            ->where('module_name', $this->module)
            ->where('status', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['category_key', 'label', 'description', 'route']);

        $itemsByCategory = $categoryRows->isEmpty()
            ? []
            : $this->visibleItemsByCategory(
                $subInstituteId,
                $userId,
                (string) $request->input('user_profile_name', '')
            );

        $categories = $categoryRows->map(fn ($category) => [
            'key' => (string) $category->category_key,
            'label' => (string) $category->label,
            'description' => (string) ($category->description ?? ''),
            'route' => (string) ($category->route ?? ''),
            'items' => $itemsByCategory[$category->category_key] ?? [],
        ])->all();

        return response()->json([
            'status' => 1,
            'data' => [
                'modules' => $directory,
                'module' => $module,
                'categories' => $categories,
            ],
        ]);
    }

    /**
     * Every configured module: its slug, the level-2 tblmenumaster row it is,
     * and that row's own name and legacy link.
     *
     * The name comes from tblmenumaster rather than from the category rows so
     * a school that renamed a module in its own menu sees its own label, the
     * same one the sidebar shows. The `link` travels with it because the
     * frontend's Intelligence matcher reads the label, the legacy link and the
     * module's own routes together — the same three inputs it reads when
     * building the sidebar, so the two can only ever agree.
     *
     * @return list<array{module_name:string,level2_menu_id:int,label:string,link:string}>
     */
    private function directory(): array
    {
        $rows = DB::table('fees_menu_categories')
            ->where('status', 1)
            ->whereNotNull('level2_menu_id')
            ->where('level2_menu_id', '>', 0)
            ->groupBy('module_name', 'level2_menu_id')
            ->orderBy('module_name')
            ->get(['module_name', 'level2_menu_id']);

        if ($rows->isEmpty()) {
            return [];
        }

        $menus = DB::table('tblmenumaster')
            ->whereIn('id', $rows->pluck('level2_menu_id')->all())
            ->get(['id', 'name', 'link'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($menus) {
            $menu = $menus[$row->level2_menu_id] ?? null;

            return [
                'module_name' => (string) $row->module_name,
                'level2_menu_id' => (int) $row->level2_menu_id,
                'label' => trim((string) ($menu->name ?? '')),
                'link' => trim((string) ($menu->link ?? '')),
            ];
        })->values()->all();
    }

    /**
     * Which module this request is about, by slug or by level-2 menu id.
     *
     * Returns null when neither was given, or when what was given names no
     * configured module — the caller then gets the directory and an empty
     * category list, which is the same answer as "this module keeps its
     * existing navigation".
     *
     * @param  list<array{module_name:string,level2_menu_id:int,label:string,link:string}>  $directory
     * @return array{module_name:string,level2_menu_id:int,label:string,link:string}|null
     */
    private function resolveModule(Request $request, array $directory): ?array
    {
        $slug = strtolower(trim((string) $request->input('module_name', '')));
        if ($slug !== '') {
            foreach ($directory as $entry) {
                if (strtolower($entry['module_name']) === $slug) {
                    return $entry;
                }
            }

            return null;
        }

        $level2MenuId = (int) $request->input('level2_menu_id', 0);
        if ($level2MenuId > 0) {
            foreach ($directory as $entry) {
                if ($entry['level2_menu_id'] === $level2MenuId) {
                    return $entry;
                }
            }
        }

        return null;
    }
}
