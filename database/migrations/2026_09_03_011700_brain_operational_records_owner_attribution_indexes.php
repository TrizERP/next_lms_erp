<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_09_01_000100_operational_records_owner_attribution_indexes.php
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
 * The second way a record names its department: through the person who handled it.
 *
 * WHY THIS EXISTS. `department_label` (2026_08_30_000100) reads the owning unit the
 * source export STATED. That is authoritative where it is present, and on this ERP
 * it is absent for a third of the rows — Fiber Valley's 16,505 `job_order` records
 * carry no department at all. They are not unattributable: every one of their nine
 * distinct `owner_name` values is a person on the CST - FVCPL roster, so the work
 * belongs to that unit as certainly as if the export had said so.
 *
 * That is a DIFFERENT attribution basis, not a fuzzier version of the same one, and
 * DepartmentWorkAttribution publishes it as such — "work handled by this unit's
 * people" beside "work booked to this unit's name". Neither is inferred from the
 * other and the screen says which one a figure came from.
 *
 * WHAT THESE INDEXES ARE FOR. Owner attribution means `owner_name IN (<the unit's
 * roster>)`, and the existing (tenant_id, dataset, owner_name) index stops exactly
 * one column short of every question worth asking of it. MEASURED ON THE LIVE
 * DATABASE, tenant 1000018, the nine owners of `job_order`:
 *
 *     GROUP BY status                      92.0s  ->  covered by ..._owner_status
 *     AVG(closed_at - occurred_at)         80.9s  ->  covered by ..._owner_time
 *
 * Both were reading `status` and `closed_at` off the clustered index, where each
 * row drags its inline `payload` longtext through the buffer pool. Extending the
 * index by the columns the aggregate selects is the whole fix; it is the same
 * lesson as OperationalIntelligence::fieldSupportPerDataset(), applied to the owner
 * axis instead of the dataset axis.
 *
 * IDEMPOTENT AND MYSQL-ONLY, like every index migration beside it: the test suite
 * runs on SQLite, where these buy nothing and the information_schema probe does not
 * exist.
 */
return new class extends Migration
{
    private const TABLE = 'hpbrain_operational_records';

    /**
     * @var array<string, string> index name => column list
     *
     * The trailing columns are the payload of the aggregate, not decoration:
     *
     *   (tenant_id, dataset, owner_name, status)
     *       "of the work this unit's people handled, how much is open, closed or
     *       cancelled" — the backlog, completion and aging figures.
     *
     *   (tenant_id, dataset, owner_name, occurred_at, closed_at)
     *       the received-vs-resolved weekly series and the turnaround average.
     *       Both timestamps are in the index so neither aggregate touches a row.
     */
    private const INDEXES = [
        'idx_oprec_tenant_ds_owner_status' => 'tenant_id, dataset, owner_name, status',
        'idx_oprec_tenant_ds_owner_time'   => 'tenant_id, dataset, owner_name, occurred_at, closed_at',
    ];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        /*
          `lock_wait_timeout`, for the reason stated at length on
          2026_08_30_000100: a blocked ALTER parks at the head of the metadata
          lock queue and every later query — session reads included, so logins
          too — queues behind it. Ten seconds, then give up and report; re-running
          is safe because hasIndex() skips what already exists.

          These two take roughly six minutes each to BUILD on the live table.
          LOCK=NONE is what keeps that from being six minutes of downtime.
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
        if (! Schema::hasTable(self::TABLE) || DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (array_keys(self::INDEXES) as $name) {
            if ($this->hasIndex($name)) {
                DB::unprepared('ALTER TABLE '.self::TABLE.' DROP INDEX '.$name);
            }
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
