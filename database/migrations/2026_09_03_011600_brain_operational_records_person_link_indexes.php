<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_31_000100_operational_records_person_link_indexes.php
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
 * The indexes a person's profile is read through.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE DEFECT THIS FIXES
 *
 * Opening one person took minutes, and the whole cost was one query. A person is
 * linked to imported records by three columns — the reference the record names,
 * who handled it, and who supervised it — and the profile asked for all three at
 * once:
 *
 *     WHERE tenant_id = ?
 *       AND (subject_ref = ? OR owner_name = ? OR supervisor_name = ?)
 *
 * No single index can serve an OR across three different columns, so the
 * optimizer abandoned all of them and read 335,856 rows off the clustered index,
 * each one dragging its inline JSON payload through the buffer pool.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WHY THE EXISTING INDEXES DID NOT HELP
 *
 * There already are `(tenant_id, dataset, owner_name)` and its two siblings, and
 * they look like they cover this. They cannot: `dataset` sits BETWEEN the tenant
 * and the name, and the profile does not filter on a dataset — it wants every
 * dataset this person appears in. A gap in the middle of a composite key ends
 * the seek, so `owner_name` is reduced to a filter applied while scanning all
 * 335,856 of the tenant's index entries. Measured on Fiber Valley:
 *
 *     zero-match lookup, existing index      3,767 ms   ← the scan alone
 *     15,116-match lookup, + occurred_at    92,150 ms   ← plus row lookups
 *
 * The first number is the tell. It is the cost of finding NOTHING, so it is pure
 * scan, and no amount of narrowing the result could have reduced it.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WHAT THESE ARE
 *
 * `(tenant_id, <link column>, dataset, occurred_at)` — the same columns, in the
 * order this query actually uses them. The name becomes a seek key rather than a
 * filter, and `dataset` and `occurred_at` follow it so the per-dataset rollup
 * (count, first seen, last seen) is answered from the index without touching a
 * row. That is what removes the 92-second half: the row lookups were only ever
 * needed because `occurred_at` was not in the index.
 *
 * IDEMPOTENT AND MYSQL-ONLY, matching the index migrations beside it. SQLite has
 * no ALTER ... ADD INDEX of this form and the test schema does not need one.
 *
 * A BOUNDED LOCK WAIT, for the reason written out in the department_label
 * migration: a blocked ALTER parks at the head of the metadata-lock queue and
 * every later query queues behind it, which takes the application down rather
 * than merely delaying the migration. Ten seconds, then give up and say so.
 * Re-running picks up whatever is still missing.
 */
return new class extends Migration
{
    private const TABLE = 'hpbrain_operational_records';

    /**
     * @var array<string, string> index name => column list
     */
    private const INDEXES = [
        'idx_oprec_tenant_owner_ds_time'      => 'tenant_id, owner_name, dataset, occurred_at',
        'idx_oprec_tenant_supervisor_ds_time' => 'tenant_id, supervisor_name, dataset, occurred_at',
        'idx_oprec_tenant_subject_ds_time'    => 'tenant_id, subject_ref, dataset, occurred_at',
    ];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (array_keys(self::INDEXES) as $name) {
            if (! $this->columnsExist(self::INDEXES[$name])) {
                return;
            }
        }

        $previous = DB::selectOne('SELECT @@SESSION.lock_wait_timeout AS t');
        DB::unprepared('SET SESSION lock_wait_timeout = 10');

        try {
            foreach (self::INDEXES as $name => $columns) {
                if ($this->hasIndex($name)) {
                    continue;
                }

                DB::unprepared(
                    'ALTER TABLE '.self::TABLE.' ADD INDEX '.$name.' ('.$columns.'), ALGORITHM=INPLACE, LOCK=NONE',
                );
            }
        } finally {
            DB::unprepared('SET SESSION lock_wait_timeout = '.(int) ($previous->t ?? 31536000));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (array_keys(self::INDEXES) as $name) {
            if ($this->hasIndex($name)) {
                DB::unprepared('ALTER TABLE '.self::TABLE.' DROP INDEX '.$name);
            }
        }
    }

    private function columnsExist(string $columns): bool
    {
        foreach (explode(',', $columns) as $column) {
            if (! Schema::hasColumn(self::TABLE, trim($column))) {
                return false;
            }
        }

        return true;
    }

    private function hasIndex(string $name): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS n
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND INDEX_NAME = ?',
            [self::TABLE, $name],
        );

        return $row !== null && (int) $row->n > 0;
    }
};
