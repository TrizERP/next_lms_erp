<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes `fees_menu_categories` / `fees_menu_category_items` multi-module.
 *
 * Both tables started Fees-only: a category was unique by `category_key`
 * alone, and an item unique by `(category_key, menu_id)`. The Teach/Learn
 * module now reuses the same two tables for its own category bar instead of
 * getting its own pair, so a `module_name` column is added and both unique
 * constraints are widened to include it — otherwise Teach/Learn's
 * `master-setup` category (say) would collide with Fees' own `master-setup`
 * row instead of being a distinct row.
 *
 * Existing rows default to `module_name = 'fees'`, so no backfill is needed:
 * the column default does it.
 *
 * Every step below is independently idempotent and driven off the schema it
 * actually finds, not off a single up-front assumption — an earlier run of
 * this migration was killed mid-flight in production (a stuck ALTER, since
 * cleared) and left the two tables in different states: `fees_menu_categories`
 * already had `module_name` added but still carried its original narrow
 * unique index, while `fees_menu_category_items` was never touched at all.
 * Gating the constraint swap on "does the column already exist" (the
 * original version of this migration did that, implicitly, by doing both in
 * one guarded block) would have skipped the constraint fix forever once the
 * column existed. Each of the four steps (column x2, constraint x2) now
 * checks its own actual current state — the column via Schema::hasColumn(),
 * the constraint via the live index name from information_schema — so this
 * migration is safe to run against an untouched database, this exact
 * partially-migrated one, or an already fully-migrated one.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->migrateCategoriesTable();
        $this->migrateItemsTable();
    }

    public function down(): void
    {
        if (Schema::hasTable('fees_menu_category_items') && Schema::hasColumn('fees_menu_category_items', 'module_name')) {
            if ($this->indexExists('fees_menu_category_items', 'fees_menu_category_items_module_unique')) {
                Schema::table('fees_menu_category_items', function (Blueprint $table) {
                    $table->dropUnique('fees_menu_category_items_module_unique');
                });
            }

            if ($this->indexExists('fees_menu_category_items', 'fees_menu_category_items_module_category_index')) {
                Schema::table('fees_menu_category_items', function (Blueprint $table) {
                    $table->dropIndex('fees_menu_category_items_module_category_index');
                });
            }

            Schema::table('fees_menu_category_items', function (Blueprint $table) {
                $table->dropColumn('module_name');
            });

            if (! $this->indexExists('fees_menu_category_items', 'fees_menu_category_items_unique')) {
                Schema::table('fees_menu_category_items', function (Blueprint $table) {
                    $table->unique(['category_key', 'menu_id'], 'fees_menu_category_items_unique');
                });
            }
        }

        if (Schema::hasTable('fees_menu_categories') && Schema::hasColumn('fees_menu_categories', 'module_name')) {
            if ($this->indexExists('fees_menu_categories', 'fees_menu_categories_module_key_unique')) {
                Schema::table('fees_menu_categories', function (Blueprint $table) {
                    $table->dropUnique('fees_menu_categories_module_key_unique');
                });
            }

            Schema::table('fees_menu_categories', function (Blueprint $table) {
                $table->dropColumn('module_name');
            });

            if (! $this->indexExists('fees_menu_categories', 'fees_menu_categories_category_key_unique')) {
                Schema::table('fees_menu_categories', function (Blueprint $table) {
                    $table->unique(['category_key'], 'fees_menu_categories_category_key_unique');
                });
            }
        }
    }

    private function migrateCategoriesTable(): void
    {
        if (! Schema::hasTable('fees_menu_categories')) {
            return;
        }

        // Step 1: the column. Independently idempotent — only runs if missing.
        if (! Schema::hasColumn('fees_menu_categories', 'module_name')) {
            Schema::table('fees_menu_categories', function (Blueprint $table) {
                $table->string('module_name', 32)->default('fees')->after('id');
            });
        }

        // Step 2: the constraint. Independently idempotent — driven off which
        // index actually exists right now, regardless of whether step 1 ran
        // just above or ran in an earlier, separate deployment.
        $hasOldUnique = $this->indexExists('fees_menu_categories', 'fees_menu_categories_category_key_unique');
        $hasNewUnique = $this->indexExists('fees_menu_categories', 'fees_menu_categories_module_key_unique');

        if ($hasOldUnique) {
            Schema::table('fees_menu_categories', function (Blueprint $table) {
                $table->dropUnique('fees_menu_categories_category_key_unique');
            });
        }

        if (! $hasNewUnique) {
            Schema::table('fees_menu_categories', function (Blueprint $table) {
                $table->unique(['module_name', 'category_key'], 'fees_menu_categories_module_key_unique');
            });
        }
    }

    private function migrateItemsTable(): void
    {
        if (! Schema::hasTable('fees_menu_category_items')) {
            return;
        }

        // Step 1: the column.
        if (! Schema::hasColumn('fees_menu_category_items', 'module_name')) {
            Schema::table('fees_menu_category_items', function (Blueprint $table) {
                $table->string('module_name', 32)->default('fees')->after('id');
            });
        }

        // Step 2: the unique constraint.
        $hasOldUnique = $this->indexExists('fees_menu_category_items', 'fees_menu_category_items_unique');
        $hasNewUnique = $this->indexExists('fees_menu_category_items', 'fees_menu_category_items_module_unique');

        if ($hasOldUnique) {
            Schema::table('fees_menu_category_items', function (Blueprint $table) {
                $table->dropUnique('fees_menu_category_items_unique');
            });
        }

        if (! $hasNewUnique) {
            Schema::table('fees_menu_category_items', function (Blueprint $table) {
                $table->unique(['module_name', 'category_key', 'menu_id'], 'fees_menu_category_items_module_unique');
            });
        }

        // Step 3: the lookup index the controller's join relies on.
        if (! $this->indexExists('fees_menu_category_items', 'fees_menu_category_items_module_category_index')) {
            Schema::table('fees_menu_category_items', function (Blueprint $table) {
                $table->index(['module_name', 'category_key'], 'fees_menu_category_items_module_category_index');
            });
        }
    }

    /**
     * Whether an index/constraint with this exact name currently exists on
     * the table — read from information_schema rather than assumed from
     * column presence, so a step that already ran (in a prior deploy, or in
     * the run that got killed) is correctly skipped, and a step that didn't
     * is correctly performed, independent of every other step's state.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS count FROM information_schema.STATISTICS '
                .'WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $indexName]
        );

        return $row !== null && (int) $row->count > 0;
    }
};
