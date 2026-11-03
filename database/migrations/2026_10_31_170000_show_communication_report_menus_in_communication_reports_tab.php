<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the Communication module's "Reports" tab (`/modules/communication/reports`) list
 * the same report screens that the "Communication Report" module already has.
 *
 * WHY IT WAS EMPTY
 * The report menus hang under the level-2 menu "Communication Report"
 * (which is module `communication-report`, level2_menu_id 307). The Communication module is
 * the level-2 menu "Communication" (id 13); it has a `reports` category row but all 7 menus
 * under Communication are entry forms (Send SMS, Send Email, etc.), so the Reports category
 * had 0 items and rendered empty ("Nothing here yet").
 *
 * The fix is the curation overlay that AbstractMenuCategoryApiController reads: explicit rows
 * in `fees_menu_category_items` let one module's bar borrow another's screens.
 * The 5 report items:
 *   - 192: Email Report (/easy_com/email_report)
 *   - 16:  Send SMS Report (/easy_com/send_sms_report)
 *   - 234: Notification Report (/easy_com/notification_report)
 *   - 308: Register Parent Report (/easy_com/register_parent_report)
 *   - 424: WhatsApp Report (/easy_com/whatsapp_report)
 * are copied from `communication-report`'s `reports` category.
 *
 * Idempotent; `down()` removes only the rows this adds.
 */
return new class extends Migration
{
    private const TARGET_MODULE = 'communication';
    private const SOURCE_MODULE = 'communication-report';
    private const CATEGORY_KEY = 'reports';

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        // 1. Ensure fees_menu_categories has the reports category for communication
        if (Schema::hasTable('fees_menu_categories')) {
            $cat = DB::table('fees_menu_categories')
                ->where('module_name', self::TARGET_MODULE)
                ->where('category_key', self::CATEGORY_KEY)
                ->first();

            if ($cat === null) {
                $level2MenuId = DB::table('fees_menu_categories')
                    ->where('module_name', self::TARGET_MODULE)
                    ->whereNotNull('level2_menu_id')
                    ->value('level2_menu_id') ?: 13;

                DB::table('fees_menu_categories')->insert([
                    'module_name' => self::TARGET_MODULE,
                    'level2_menu_id' => $level2MenuId,
                    'category_key' => self::CATEGORY_KEY,
                    'label' => 'Reports',
                    'description' => 'Communication reporting and analysis.',
                    'route' => '/modules/'.self::TARGET_MODULE.'/'.self::CATEGORY_KEY,
                    'sort_order' => 5,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. Fetch source report items from communication-report
        $source = DB::table('fees_menu_category_items')
            ->where('module_name', self::SOURCE_MODULE)
            ->where('category_key', self::CATEGORY_KEY)
            ->where('status', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['menu_id', 'sort_order']);

        $items = $source->map(fn ($r) => ['menu_id' => (int) $r->menu_id, 'sort_order' => (int) $r->sort_order]);

        // Fallback: if source items are missing, use known menu IDs
        if ($items->isEmpty()) {
            $items = collect([
                ['menu_id' => 192, 'sort_order' => 1],
                ['menu_id' => 16,  'sort_order' => 2],
                ['menu_id' => 234, 'sort_order' => 3],
                ['menu_id' => 308, 'sort_order' => 4],
                ['menu_id' => 424, 'sort_order' => 5],
            ]);
        }

        // 3. Insert or update items for TARGET_MODULE
        $now = now();
        foreach ($items as $item) {
            $existing = DB::table('fees_menu_category_items')
                ->where('module_name', self::TARGET_MODULE)
                ->where('category_key', self::CATEGORY_KEY)
                ->where('menu_id', $item['menu_id'])
                ->first();

            if ($existing) {
                DB::table('fees_menu_category_items')
                    ->where('id', $existing->id)
                    ->update([
                        'sort_order' => $item['sort_order'],
                        'status' => 1,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('fees_menu_category_items')->insert([
                    'module_name' => self::TARGET_MODULE,
                    'category_key' => self::CATEGORY_KEY,
                    'menu_id' => $item['menu_id'],
                    'sort_order' => $item['sort_order'],
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        DB::table('fees_menu_category_items')
            ->where('module_name', self::TARGET_MODULE)
            ->where('category_key', self::CATEGORY_KEY)
            ->whereIn('menu_id', [192, 16, 234, 308, 424])
            ->delete();
    }
};

