<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes Workflow, Schedular and Audit Trail from the Fees level-3 category bar.
 *
 * The three were seeded with a route each — /fees/workflow, /fees/scheduler,
 * /fees/audit-trail — and no screen was ever built at any of them, and no menu was ever
 * attached: `fees_menu_category_items` holds nought rows for all three. So they render
 * as three tabs that lead to a 404, which is worse than not offering them.
 *
 * Hidden (status = 0), not deleted — the same treatment `sop-task` was given in
 * 2026_09_05_140000_reorder_and_extend_fees_categories.php, and for the same reason: the
 * row and any future item mappings survive, so bringing a tab back when its screens exist
 * is one column update rather than a re-seed. Nothing is destroyed on a guess.
 *
 * Scoped to `module_name = 'fees'`. The table is shared with Teach/Learn and every other
 * module through that column, and these three keys exist only for Fees today — but the
 * filter is what stops this hiding another module's tab the moment one is seeded with the
 * same key.
 *
 * The remaining eight categories keep sort_order 1-9 with no gap to close, because these
 * three sit at the end of the order (10, 11, 12).
 */
return new class extends Migration
{
    private const MODULE = 'fees';

    /** The categories this migration owns, and nothing else. */
    private const CATEGORY_KEYS = ['workflow', 'schedular', 'audit-trail'];

    public function up(): void
    {
        $this->setStatus(0);
    }

    public function down(): void
    {
        $this->setStatus(1);
    }

    private function setStatus(int $status): void
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            return;
        }

        $query = DB::table('fees_menu_categories')->whereIn('category_key', self::CATEGORY_KEYS);

        // The column arrived with 2026_09_10_150000_add_module_name_to_fees_menu_category_tables.
        // An estate that has not taken that migration has a Fees-only table, where the
        // keys are unambiguous on their own.
        if (Schema::hasColumn('fees_menu_categories', 'module_name')) {
            $query->where('module_name', self::MODULE);
        }

        $query->update(['status' => $status, 'updated_at' => now()]);
    }
};
