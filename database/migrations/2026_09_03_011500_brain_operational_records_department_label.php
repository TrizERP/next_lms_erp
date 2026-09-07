<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_30_000100_operational_records_department_label.php
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
 * The department a record belongs to, promoted out of the payload into a column.
 *
 * WHY THIS EXISTS. `hpbrain_operational_records` is the richest data this product
 * holds, and until now none of it could be attributed to a department. The
 * DepartmentIntelligenceMetrics docblock says so explicitly: the table "carries NO
 * person or department foreign key", so a department's operational activity had to
 * be left out of its score rather than approximated by a fuzzy name join.
 *
 * That was true of the COLUMNS. It was never true of the DATA. Every row this
 * product ingests through OperationalRecordLoader carries the owning unit inside
 * its payload — the ERP exports name it, and the importer preserves it verbatim:
 *
 *     {"department":"Help Desk","entity_type":"Complaint", ...}
 *
 * So the attribution is authoritative and already present; it was simply
 * unqueryable, sitting inside a longtext blob with no index. Reading it meant
 * JSON_EXTRACT over every row of the tenant on every page load.
 *
 * WHAT THIS IS NOT. It is not a derived guess, not a name match, and not new data.
 * The backfill copies `$.department` — the value the source system stated — into a
 * column, and the loader writes the same value on every future import so the two
 * can never diverge. A row whose payload does not name a department gets NULL, and
 * every consumer treats NULL as "the source did not say", never as a department
 * called nothing. See BackfillRecordDepartments for the copy, and
 * OperationalRecordLoader::load() for the forward path.
 *
 * NULLABLE, AND NULL IS THE CORRECT ANSWER FOR MOST TENANTS. Nothing requires a
 * source system to record departments. A tenant whose exports do not carry one
 * ends up with the column entirely null, `support.department` false in the
 * intelligence layer, and department-attributed metrics suppressed with a reason
 * rather than scored as zero.
 *
 * TWO INDEXES, BOTH TENANT-LEADING, matching every other index on this table.
 * Their column order is stated on the constant below, and it is load-bearing: an
 * index that stops at `department_label` forces the GROUP BY back onto the
 * clustered index, where every row drags its inline JSON payload through the
 * buffer pool. That was measured on the live database as the difference between
 * seconds and not completing.
 *
 * IDEMPOTENT AND MYSQL-ONLY, for the same reason as the index migrations beside
 * it: the test suite runs on SQLite, where these buy nothing and the
 * information_schema probe does not exist.
 */
return new class extends Migration
{
    private const TABLE = 'hpbrain_operational_records';

    private const COLUMN = 'department_label';

    /**
     * @var array<string, string> index name => column list
     *
     * BOTH ARE COVERING INDEXES FOR A SPECIFIC GROUP BY, and the column order is
     * the reason they work rather than incidental:
     *
     *   (tenant_id, department_label, status, dataset)
     *       "what did each unit do, and how much of it finished" — the query
     *       behind every department completion figure. A three-column index
     *       ending at department_label cannot serve it, because `status` would
     *       have to come from the row, and the row is on the clustered index
     *       where the payload longtext lives. That single difference was measured
     *       at over 460 seconds against seconds; see
     *       OperationalIntelligence::fieldSupportPerDataset().
     *
     *   (tenant_id, department_label, occurred_at)
     *       the per-unit monthly volume series, for the same reason.
     */
    private const INDEXES = [
        'idx_oprec_tenant_department_status' => 'tenant_id, department_label, status, dataset',
        'idx_oprec_tenant_department_time'   => 'tenant_id, department_label, occurred_at',
    ];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            Schema::table(self::TABLE, function ($table): void {
                $table->string(self::COLUMN, 191)->nullable()->after('supervisor_name');
            });
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        /*
          A BLOCKED `ALTER` BLOCKS THE WHOLE APPLICATION, INCLUDING LOGIN.

          Adding an index needs a brief exclusive metadata lock on the table. If
          a long-running SELECT already holds a shared one, the ALTER waits — and
          because metadata-lock waits are served in order, EVERY query that
          arrives after it queues behind it too, even the ones the running SELECT
          would not have blocked on its own. On this table, whose aggregates take
          minutes until these very indexes exist, that turned a routine migration
          into a total outage: sessions could not be read, so nobody could log in.

          `lock_wait_timeout` bounds that. The ALTER now gives up after ten
          seconds and this migration reports it, instead of parking at the head
          of the lock queue for as long as the longest reader runs. Re-running it
          is safe and picks up where it left off: `hasIndex()` skips whatever
          already exists.

          LOCK=NONE keeps readers and writers running for the build itself, which
          is the long part. It is the acquisition, not the build, that has to be
          fought for.
        */
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
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            foreach (array_keys(self::INDEXES) as $name) {
                if ($this->hasIndex($name)) {
                    DB::unprepared('ALTER TABLE '.self::TABLE.' DROP INDEX '.$name);
                }
            }
        }

        if (Schema::hasColumn(self::TABLE, self::COLUMN)) {
            Schema::table(self::TABLE, function ($table): void {
                $table->dropColumn(self::COLUMN);
            });
        }
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
