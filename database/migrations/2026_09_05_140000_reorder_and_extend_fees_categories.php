<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reorders the Fees categories, renames "Transactional Data" to "Operations",
 * and adds "Communication" and "AI Stack".
 *
 * Final order:
 *   1 Onboarding   2 Process Builder   3 Master Setup   4 Operations
 *   5 Reports      6 Intelligence      7 Help Guide / Support
 *   8 Communication   9 AI Stack
 *
 * The rename carries through to the key and the route, not just the label, so
 * the category is called the same thing everywhere: category_key
 * transactional-data -> operations, route /fees/transactional-data ->
 * /fees/operations. The key is referenced by fees_menu_category_items, so those
 * twelve rows are re-pointed in the same statement — the menus themselves are
 * untouched and stay in the category, in order.
 *
 * "SOP / Task" is not in the requested list. It is hidden (status = 0) rather
 * than deleted: the row and any future item mappings survive, so restoring it
 * is a single column update. Nothing is destroyed on a guess.
 *
 * Communication and AI Stack start with no menus. No Fees menu in
 * tblmenumaster belongs to either — the only near match is "Workflows" under AI
 * Administration, which is the AI module's own menu — and inventing Fees menus
 * to fill a tab would be worse than an honest empty state. Each renders the
 * same "No screens available yet" panel until real screens exist; attaching one
 * later is a row in fees_menu_category_items, no code change.
 *
 * Ordering is declared for every category rather than nudged relative to the
 * current values, so re-running cannot make the order drift.
 */
return new class extends Migration
{
    private const RENAME_FROM = 'transactional-data';

    private const RENAME_TO = 'operations';

    /** Categories created here: key => [label, description, route]. */
    private const NEW_CATEGORIES = [
        'communication' => [
            'Communication',
            'Fees notices, reminders and parent communication.',
            '/fees/communication',
        ],
        'ai-stack' => [
            'AI Stack',
            'AI services and automation for the fees module.',
            '/fees/ai-stack',
        ],
    ];

    /** category_key => sort_order. The full intended order, applied as-is. */
    private const ORDER = [
        'onboarding' => 1,
        'process-builder' => 2,
        'master-setup' => 3,
        'operations' => 4,
        'reports' => 5,
        'intelligence' => 6,
        'help-guide-support' => 7,
        'communication' => 8,
        'ai-stack' => 9,
    ];

    /** Not in the requested list: hidden, never deleted. */
    private const HIDDEN = ['sop-task'];

    public function up(): void
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            return;
        }

        $hasRoute = Schema::hasColumn('fees_menu_categories', 'route');

        DB::transaction(function () use ($hasRoute) {
            $this->renameTransactionalData($hasRoute);

            foreach (self::NEW_CATEGORIES as $key => [$label, $description, $route]) {
                if (DB::table('fees_menu_categories')->where('category_key', $key)->exists()) {
                    continue;
                }

                $row = [
                    'category_key' => $key,
                    'label' => $label,
                    'description' => $description,
                    'sort_order' => self::ORDER[$key],
                    'status' => 1,
                    'created_at' => now(),
                ];

                if ($hasRoute) {
                    $row['route'] = $route;
                }

                DB::table('fees_menu_categories')->insert($row);
            }

            foreach (self::ORDER as $key => $sortOrder) {
                DB::table('fees_menu_categories')
                    ->where('category_key', $key)
                    ->update(['sort_order' => $sortOrder, 'status' => 1, 'updated_at' => now()]);
            }

            DB::table('fees_menu_categories')
                ->whereIn('category_key', self::HIDDEN)
                ->update(['status' => 0, 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            return;
        }

        $hasRoute = Schema::hasColumn('fees_menu_categories', 'route');

        DB::transaction(function () use ($hasRoute) {
            $newKeys = array_keys(self::NEW_CATEGORIES);

            if (Schema::hasTable('fees_menu_category_items')) {
                DB::table('fees_menu_category_items')->whereIn('category_key', $newKeys)->delete();
            }

            DB::table('fees_menu_categories')->whereIn('category_key', $newKeys)->delete();

            // Undo the rename.
            if (DB::table('fees_menu_categories')->where('category_key', self::RENAME_TO)->exists()) {
                $update = [
                    'category_key' => self::RENAME_FROM,
                    'label' => 'Transactional Data',
                    'updated_at' => now(),
                ];

                if ($hasRoute) {
                    $update['route'] = '/fees/'.self::RENAME_FROM;
                }

                DB::table('fees_menu_categories')->where('category_key', self::RENAME_TO)->update($update);

                if (Schema::hasTable('fees_menu_category_items')) {
                    DB::table('fees_menu_category_items')
                        ->where('category_key', self::RENAME_TO)
                        ->update(['category_key' => self::RENAME_FROM, 'updated_at' => now()]);
                }
            }

            DB::table('fees_menu_categories')
                ->whereIn('category_key', self::HIDDEN)
                ->update(['status' => 1, 'updated_at' => now()]);

            // The order this migration replaced.
            $previous = [
                'process-builder' => 1,
                'onboarding' => 2,
                'master-setup' => 3,
                'transactional-data' => 4,
                'reports' => 5,
                'intelligence' => 6,
                'help-guide-support' => 7,
                'sop-task' => 8,
            ];

            foreach ($previous as $key => $sortOrder) {
                DB::table('fees_menu_categories')
                    ->where('category_key', $key)
                    ->update(['sort_order' => $sortOrder, 'updated_at' => now()]);
            }
        });
    }

    /**
     * Renames the category and re-points the item rows that reference its key.
     * No-op once already renamed, so the migration stays re-runnable.
     */
    private function renameTransactionalData(bool $hasRoute): void
    {
        $existing = DB::table('fees_menu_categories')
            ->where('category_key', self::RENAME_FROM)
            ->first();

        if ($existing === null) {
            return;
        }

        $update = [
            'category_key' => self::RENAME_TO,
            'label' => 'Operations',
            'description' => 'Day-to-day fees collection, cancellation, refunds and NACH operations.',
            'updated_at' => now(),
        ];

        if ($hasRoute) {
            $update['route'] = '/fees/'.self::RENAME_TO;
        }

        DB::table('fees_menu_categories')->where('id', $existing->id)->update($update);

        if (Schema::hasTable('fees_menu_category_items')) {
            DB::table('fees_menu_category_items')
                ->where('category_key', self::RENAME_FROM)
                ->update(['category_key' => self::RENAME_TO, 'updated_at' => now()]);
        }
    }
};
