<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds "Process Builder" as the first Fees category, above Onboarding.
 *
 * It starts with no menus. The only process/workflow menu in tblmenumaster is
 * "Workflows" under AI Administration, which belongs to the AI module — pulling
 * it into Fees would change another module, and inventing a Fees menu for the
 * sake of a non-empty tab is worse than an honest empty state. The category
 * renders the same "No screens available yet" panel as Onboarding, Help Guide /
 * Support and SOP / Task until real Fees process screens exist; attaching them
 * later is one row in fees_menu_category_items, no code change.
 *
 * Ordering is declared for all eight categories rather than shifting the
 * existing rows by one, so re-running cannot make the order drift.
 */
return new class extends Migration
{
    private const CATEGORY_KEY = 'process-builder';

    /** category_key => sort_order. The full intended order, applied as-is. */
    private const ORDER = [
        'process-builder' => 1,
        'onboarding' => 2,
        'master-setup' => 3,
        'transactional-data' => 4,
        'reports' => 5,
        'intelligence' => 6,
        'help-guide-support' => 7,
        'sop-task' => 8,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            return;
        }

        $hasRoute = Schema::hasColumn('fees_menu_categories', 'route');

        if (! DB::table('fees_menu_categories')->where('category_key', self::CATEGORY_KEY)->exists()) {
            $row = [
                'category_key' => self::CATEGORY_KEY,
                'label' => 'Process Builder',
                'description' => 'Design and manage fees processes and approval flows.',
                'sort_order' => self::ORDER[self::CATEGORY_KEY],
                'status' => 1,
                'created_at' => now(),
            ];

            if ($hasRoute) {
                $row['route'] = '/fees/'.self::CATEGORY_KEY;
            }

            DB::table('fees_menu_categories')->insert($row);
        }

        foreach (self::ORDER as $key => $sortOrder) {
            DB::table('fees_menu_categories')
                ->where('category_key', $key)
                ->update(['sort_order' => $sortOrder, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            return;
        }

        if (Schema::hasTable('fees_menu_category_items')) {
            DB::table('fees_menu_category_items')->where('category_key', self::CATEGORY_KEY)->delete();
        }

        DB::table('fees_menu_categories')->where('category_key', self::CATEGORY_KEY)->delete();

        // Restore the original 1..7 order the earlier migration seeded.
        $previous = [
            'onboarding' => 1,
            'master-setup' => 2,
            'transactional-data' => 3,
            'reports' => 4,
            'intelligence' => 5,
            'help-guide-support' => 6,
            'sop-task' => 7,
        ];

        foreach ($previous as $key => $sortOrder) {
            DB::table('fees_menu_categories')
                ->where('category_key', $key)
                ->update(['sort_order' => $sortOrder, 'updated_at' => now()]);
        }
    }
};
