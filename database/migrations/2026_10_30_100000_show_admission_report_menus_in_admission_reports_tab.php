<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the Admission module's "Reports" tab (`/modules/admission/reports`) list
 * the same report screens that the "Admission Report" module already has.
 *
 * WHY IT WAS EMPTY
 * The report menus hang under the level-2 menu "Admission Report"
 * (which is module `admission-report`). The Admission module is
 * the level-2 menu "Admission" (id 173); it has a `reports` category row but no
 * menu under Admission is report-like and `fees_menu_category_items` held nothing
 * for (admission, reports), so the tab rendered empty.
 *
 * The fix is the curation overlay that AbstractMenuCategoryApiController
 * already reads: explicit rows in `fees_menu_category_items` let one module's
 * bar borrow another's screens. The rows are copied from `admission-report`'s
 * own reports category (or from the level-2 Admission Report children in tblmenumaster),
 * so both bars show the same list in the same order and the rights/tenant filters
 * of the feed still decide who sees what.
 *
 * Idempotent; `down()` removes only the rows this adds.
 */
return new class extends Migration
{
    private const TARGET_MODULE = 'admission';
    private const SOURCE_MODULE = 'admission-report';
    private const CATEGORY_KEY = 'reports';

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        // 1. Ensure fees_menu_categories has the reports category for admission
        if (Schema::hasTable('fees_menu_categories')) {
            $admissionCat = DB::table('fees_menu_categories')
                ->where('module_name', self::TARGET_MODULE)
                ->where('category_key', self::CATEGORY_KEY)
                ->first();

            if ($admissionCat === null) {
                // Find level2_menu_id for admission
                $level2MenuId = DB::table('fees_menu_categories')
                    ->where('module_name', self::TARGET_MODULE)
                    ->whereNotNull('level2_menu_id')
                    ->value('level2_menu_id');

                if (! $level2MenuId && Schema::hasTable('tblmenumaster')) {
                    $level2MenuId = DB::table('tblmenumaster')
                        ->where('level', 2)
                        ->where('status', 1)
                        ->where(function ($query) {
                            $query->where('name', 'like', 'Admission%')
                                ->where('name', 'not like', '%Report%');
                        })
                        ->value('id');
                }

                DB::table('fees_menu_categories')->insert([
                    'module_name' => self::TARGET_MODULE,
                    'level2_menu_id' => $level2MenuId,
                    'category_key' => self::CATEGORY_KEY,
                    'label' => 'Reports',
                    'description' => 'Admission reporting and analysis.',
                    'route' => '/modules/'.self::TARGET_MODULE.'/'.self::CATEGORY_KEY,
                    'sort_order' => 5,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. First check if SOURCE_MODULE has rows in fees_menu_category_items
        $source = DB::table('fees_menu_category_items')
            ->where('module_name', self::SOURCE_MODULE)
            ->where('category_key', self::CATEGORY_KEY)
            ->where('status', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['menu_id', 'sort_order']);

        // 3. If SOURCE_MODULE has no items yet, discover from tblmenumaster
        if ($source->isEmpty() && Schema::hasTable('tblmenumaster')) {
            // Find level-2 "Admission Report" menu
            $level2ReportMenu = DB::table('tblmenumaster')
                ->where('level', 2)
                ->where('status', 1)
                ->where(function ($query) {
                    $query->where('name', 'like', '%Admission Report%')
                        ->orWhere('name', 'like', '%Admission report%')
                        ->orWhere('link', 'like', '%admission_report%');
                })
                ->first();

            $children = collect();
            if ($level2ReportMenu) {
                $children = DB::table('tblmenumaster')
                    ->where('parent_menu_id', $level2ReportMenu->id)
                    ->where('level', 3)
                    ->where('status', 1)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(['id', 'sort_order']);
            }

            // Fallback: search by known admission report links in tblmenumaster if parent didn't have them
            if ($children->isEmpty()) {
                $children = DB::table('tblmenumaster')
                    ->where('status', 1)
                    ->where(function ($query) {
                        $query->whereIn('link', [
                            'admission/admission_enquiry_report',
                            'admission_enquiry_report',
                            'admission_enquiry_report.index',
                            'show_enquiry_report',
                            'admission/admission_registration_report',
                            'admission_registration_report',
                            'admission_registration_report.index',
                            'show_form_report',
                            'admission/admission_without_con_report',
                            'admission_without_con_report',
                            'admission_without_con_report.index',
                            'show_reg_report',
                            'admission/admission_confirmation_report',
                            'admission_confirmation_report',
                            'admission_confirmation_report.index',
                            'show_con_report',
                            'admission/admission_enquiry_followup_report',
                            'admission_enquiry_followup_report',
                            'admission_enquiry_followup_report.index',
                            'show_enquiry_followup_report',
                        ])
                        ->orWhere('name', 'like', '%Admission Enquiry Report%')
                        ->orWhere('name', 'like', '%Admission Registration Report%')
                        ->orWhere('name', 'like', '%Admission Confirmation Report%')
                        ->orWhere('name', 'like', '%Without Confirmation%')
                        ->orWhere('name', 'like', '%Enquiry Follow%');
                    })
                    ->orderBy('id')
                    ->get(['id', 'sort_order']);
            }

            $order = 1;
            foreach ($children as $child) {
                $itemSort = $child->sort_order ?: $order;

                // Also populate SOURCE_MODULE for completeness
                $existsSource = DB::table('fees_menu_category_items')
                    ->where('module_name', self::SOURCE_MODULE)
                    ->where('category_key', self::CATEGORY_KEY)
                    ->where('menu_id', $child->id)
                    ->exists();

                if (! $existsSource) {
                    DB::table('fees_menu_category_items')->insert([
                        'module_name' => self::SOURCE_MODULE,
                        'category_key' => self::CATEGORY_KEY,
                        'menu_id' => $child->id,
                        'sort_order' => $itemSort,
                        'status' => 1,
                        'created_at' => now(),
                    ]);
                }

                // Insert into TARGET_MODULE (admission)
                $existsTarget = DB::table('fees_menu_category_items')
                    ->where('module_name', self::TARGET_MODULE)
                    ->where('category_key', self::CATEGORY_KEY)
                    ->where('menu_id', $child->id)
                    ->exists();

                if (! $existsTarget) {
                    DB::table('fees_menu_category_items')->insert([
                        'module_name' => self::TARGET_MODULE,
                        'category_key' => self::CATEGORY_KEY,
                        'menu_id' => $child->id,
                        'sort_order' => $itemSort,
                        'status' => 1,
                        'created_at' => now(),
                    ]);
                }

                $order++;
            }

            return;
        }

        // 4. Copy existing rows from SOURCE_MODULE to TARGET_MODULE
        foreach ($source as $row) {
            $exists = DB::table('fees_menu_category_items')
                ->where('module_name', self::TARGET_MODULE)
                ->where('category_key', self::CATEGORY_KEY)
                ->where('menu_id', $row->menu_id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('fees_menu_category_items')->insert([
                'module_name' => self::TARGET_MODULE,
                'category_key' => self::CATEGORY_KEY,
                'menu_id' => $row->menu_id,
                'sort_order' => $row->sort_order,
                'status' => 1,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        $menuIds = DB::table('fees_menu_category_items')
            ->where('module_name', self::SOURCE_MODULE)
            ->where('category_key', self::CATEGORY_KEY)
            ->pluck('menu_id');

        DB::table('fees_menu_category_items')
            ->where('module_name', self::TARGET_MODULE)
            ->where('category_key', self::CATEGORY_KEY)
            ->whereIn('menu_id', $menuIds)
            ->delete();
    }
};

