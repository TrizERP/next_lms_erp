<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_02_000200_erp_lookup_indexes.php
 * (read-only reference; that repository is not modified).
 *
 * Every table here is hpbrain_-prefixed. The Brain was designed to share a
 * database with the institute ERP, so nothing below collides with, alters or
 * duplicates an LMS table — Organization, Departments, People, Positions and
 * Students continue to live in the LMS's own tables and are reached through
 * hpbrain_entity_mappings.
 */
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes on the ERP tables the Brain reads on every screen.
 *
 * THESE TABLES ARE NOT OURS. tbluser, hrms_departments, institute_detail,
 * org_details and tbluserprofilemaster belong to the institute ERP, which
 * shares this database. No migration in this project creates them and none has
 * ever indexed them — but the Brain queries tbluser from roughly thirty call
 * sites, and every one of them filters on sub_institute_id or email. Without an
 * index those are full table scans, repeated on every login and every load of
 * the people and dashboard screens.
 *
 * BECAUSE THEY ARE NOT OURS, THIS MIGRATION IS DELIBERATELY TIMID:
 *
 *   1. It only ever CREATEs. It never drops, alters or reorders anything.
 *   2. It skips any table it cannot find, and any index naming a column that
 *      does not exist — the ERP schema may differ from what we expect.
 *   3. It skips when the LEADING COLUMN IS ALREADY INDEXED, under any name.
 *      On a table another system owns, a redundant index is not free: it costs
 *      disk and slows every INSERT and UPDATE the ERP makes. If sub_institute_id
 *      is already indexed, the scan problem is already solved and a marginally
 *      better composite is not worth imposing a second index on someone else's
 *      write path.
 *   4. Each statement is individually guarded, so one failure cannot abort the
 *      others.
 *
 * OPERATIONAL NOTE. CREATE INDEX takes a lock while it builds. On MySQL 8 that
 * is an online DDL that permits concurrent reads and writes for these index
 * types, but it still consumes I/O proportional to table size. Run it in a
 * low-traffic window. `php artisan brain:db-diagnostics` reports the row counts
 * and existing indexes to expect before you do.
 *
 * Every index below is prefixed hpb_ so it is unambiguous which system added it
 * and it can never collide with an ERP-chosen name.
 */
return new class extends Migration
{
    /**
     * table => [[index suffix, [columns...]], ...]
     *
     * Column lists mirror the actual predicates in the application:
     *   tbluser              WHERE sub_institute_id = ? AND status = 1 AND deleted_at IS NULL
     *                        WHERE email = ? AND status = 1 AND deleted_at IS NULL   (login)
     *   hrms_departments     WHERE sub_institute_id = ? AND status = 1 AND deleted_at IS NULL
     *   institute_detail     WHERE sub_institute_id = ? AND deleted_at IS NULL
     *   org_details          WHERE sub_institute_id = ?   (correlated subquery, per org row)
     *   tbluserprofilemaster WHERE sub_institute_id = ? AND status = 1
     *
     * @var array<string, array<int, array{0:string,1:array<int,string>}>>
     */
    private const INDEXES = [
        'tbluser' => [
            ['tenant', ['sub_institute_id', 'status', 'deleted_at']],
            ['email', ['email']],
        ],
        'hrms_departments' => [
            ['tenant', ['sub_institute_id', 'status', 'deleted_at']],
        ],
        'institute_detail' => [
            ['tenant', ['sub_institute_id', 'deleted_at']],
        ],
        'org_details' => [
            ['tenant', ['sub_institute_id']],
        ],
        'tbluserprofilemaster' => [
            // `name` is excluded on purpose: it is free-form text in the ERP and
            // MySQL rejects an unbounded TEXT column in a key (error 1170).
            ['tenant', ['sub_institute_id', 'status']],
        ],
    ];

    public function up(): void
    {
        // information_schema lookups below are MySQL-specific, and the suite
        // runs on a hand-built SQLite fixture that never executes migrations.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                logger()->info('erp_index_skipped_no_table', ['table' => $table]);

                continue;
            }

            foreach ($indexes as [$suffix, $columns]) {
                $name = 'hpb_'.$table.'_'.$suffix;

                $missing = array_values(array_filter(
                    $columns,
                    fn (string $c) => ! Schema::hasColumn($table, $c),
                ));

                if ($missing !== []) {
                    logger()->info('erp_index_skipped_missing_columns', [
                        'table' => $table, 'index' => $name, 'missing' => $missing,
                    ]);

                    continue;
                }

                if ($this->leadingColumnAlreadyIndexed($table, $columns[0])) {
                    logger()->info('erp_index_skipped_already_covered', [
                        'table' => $table, 'index' => $name, 'leading_column' => $columns[0],
                    ]);

                    continue;
                }

                try {
                    DB::unprepared(sprintf(
                        'CREATE INDEX %s ON %s (%s)',
                        $name,
                        $table,
                        implode(', ', $columns),
                    ));

                    logger()->info('erp_index_created', ['table' => $table, 'index' => $name]);
                } catch (\Throwable $e) {
                    logger()->warning('erp_index_failed', [
                        'table' => $table, 'index' => $name, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Is there already ANY index whose first column is this one?
     *
     * seq_in_index = 1 selects the leading column of each index, which is the
     * only position that determines whether an equality filter on that column
     * can use the index at all.
     */
    private function leadingColumnAlreadyIndexed(string $table, string $column): bool
    {
        return DB::select(
            'SELECT 1 FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = ?
                AND column_name = ? AND seq_in_index = 1
              LIMIT 1',
            [$table, $column],
        ) !== [];
    }

    /**
     * Drops only the indexes this migration added.
     *
     * Safe because of the hpb_ prefix: nothing else in either system creates a
     * name in that space, so a rollback cannot remove an ERP-owned index.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as [$suffix, $_columns]) {
                $name = 'hpb_'.$table.'_'.$suffix;

                $exists = DB::select(
                    'SELECT 1 FROM information_schema.statistics
                      WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
                      LIMIT 1',
                    [$table, $name],
                ) !== [];

                if ($exists) {
                    try {
                        DB::unprepared(sprintf('DROP INDEX %s ON %s', $name, $table));
                    } catch (\Throwable) {
                        // Nothing useful to do during a rollback.
                    }
                }
            }
        }
    }
};
