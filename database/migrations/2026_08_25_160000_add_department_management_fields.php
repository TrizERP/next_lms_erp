<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's (hp_erp) `2026_08_21_090000_add_department_management_fields.php`.
 *
 * Adds the columns Department Management's UI has been faking: `code`
 * (was a hardcoded lookup in the frontend bundle, with an initials
 * fallback), `description`, and `sort_order` (backs the "Move up / Move
 * down" controls, which had no ordering column to move anything within).
 * `head`/`employees` need no schema - `head_user_id` already exists on
 * this table and headcount is a count over `tbluser.department_id`.
 *
 * Adapted from the source:
 *  - The source's `EXPLICIT_CODES` backfill map is a fixed list of G2G's
 *    own corporate department names ("Human Resources" -> "HR", etc.).
 *    That map has no meaning against this project's K-12 school data, so
 *    it is dropped; the initials-derivation fallback (which the source
 *    also uses for anything not in its map) is kept, since it is generic.
 *  - `Schema::hasColumn()`/`hasTable()` are used directly here rather than
 *    the source's own information_schema workaround, which exists only
 *    for a MariaDB 10.1 "live" database this project does not have.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hrms_departments')) {
            return;
        }

        Schema::table('hrms_departments', function (Blueprint $table) {
            if (!Schema::hasColumn('hrms_departments', 'code')) {
                $table->string('code', 50)->nullable()->index();
            }

            if (!Schema::hasColumn('hrms_departments', 'description')) {
                $table->text('description')->nullable();
            }

            if (!Schema::hasColumn('hrms_departments', 'sort_order')) {
                $table->integer('sort_order')->default(0)->index();
            }
        });

        if (Schema::hasColumn('hrms_departments', 'head_user_id')) {
            $this->addIndexIfMissing(
                'hrms_departments_head_user_id_index',
                'CREATE INDEX `hrms_departments_head_user_id_index` ON `hrms_departments` (`head_user_id`)'
            );
        }

        // The hot query is `WHERE sub_institute_id = ? AND status = ?`. Every
        // existing index on this table is single-column, so that query could
        // only use one of them.
        $this->addIndexIfMissing(
            'hrms_departments_tenant_status_index',
            'CREATE INDEX `hrms_departments_tenant_status_index` ON `hrms_departments` (`sub_institute_id`, `status`)'
        );

        $this->backfillCodes();
        $this->backfillSortOrder();
    }

    public function down(): void
    {
        if (!Schema::hasTable('hrms_departments')) {
            return;
        }

        if ($this->indexExists('hrms_departments_tenant_status_index')) {
            DB::statement('DROP INDEX `hrms_departments_tenant_status_index` ON `hrms_departments`');
        }

        Schema::table('hrms_departments', function (Blueprint $table) {
            foreach (['code', 'description', 'sort_order'] as $column) {
                if (Schema::hasColumn('hrms_departments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        // head_user_id's index is deliberately left in place - it may predate
        // this migration, so dropping it here could take away something this
        // migration did not add.
    }

    /**
     * Fill `code` for every row that has none. Chunked and scoped to
     * `code IS NULL`, so re-running it is a no-op and a code an admin has
     * since edited by hand is never overwritten.
     */
    private function backfillCodes(): void
    {
        DB::table('hrms_departments')
            ->select('id', 'department')
            ->whereNull('code')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $code = $this->deriveCode((string) $row->department);

                    if ($code === '') {
                        continue;
                    }

                    DB::table('hrms_departments')
                        ->where('id', $row->id)
                        ->update(['code' => $code]);
                }
            });
    }

    /** Initials of each word in the department name, e.g. "Human Resources" -> "HR". */
    private function deriveCode(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '';
        }

        $parts = preg_split('/[\s&]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $initials = '';
        foreach ($parts as $part) {
            $initials .= mb_substr($part, 0, 1);
        }

        return mb_substr(mb_strtoupper($initials), 0, 6);
    }

    /**
     * Number each department sequentially within its own (tenant, parent)
     * group, ordered by name - the order the list was already displayed in,
     * so nothing visibly moves on first load. Only touches rows still at the
     * 0 default, so a hand-ordered tree survives a re-run.
     */
    private function backfillSortOrder(): void
    {
        $groups = DB::table('hrms_departments')
            ->select('sub_institute_id', 'parent_id')
            ->where('sort_order', 0)
            ->groupBy('sub_institute_id', 'parent_id')
            ->get();

        foreach ($groups as $group) {
            $rows = DB::table('hrms_departments')
                ->select('id')
                ->where('sub_institute_id', $group->sub_institute_id)
                ->where('parent_id', $group->parent_id)
                ->where('sort_order', 0)
                ->orderBy('department')
                ->orderBy('id')
                ->get();

            $position = 1;
            foreach ($rows as $row) {
                DB::table('hrms_departments')
                    ->where('id', $row->id)
                    ->update(['sort_order' => $position++]);
            }
        }
    }

    private function addIndexIfMissing(string $indexName, string $sql): void
    {
        if ($this->indexExists($indexName)) {
            return;
        }

        DB::statement($sql);
    }

    private function indexExists(string $indexName): bool
    {
        $found = DB::select(
            'SELECT 1 FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND INDEX_NAME = ?
              LIMIT 1',
            ['hrms_departments', $indexName]
        );

        return $found !== [];
    }
};
