<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_18_000400_operational_records_freshness_index.php
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
 * One index for the query that runs on EVERY intelligence request.
 *
 * THE QUERY. OrganizationDataProfiler::dataVersion() computes the fingerprint
 * that keys the intelligence cache, and it opens with:
 *
 *     SELECT COUNT(*), MAX(updated_date)
 *       FROM hpbrain_operational_records
 *      WHERE tenant_id = ?
 *
 * Its own comment explains why it is only two round trips: it runs on every
 * request *including cache hits*, so its cost is the floor under every
 * intelligence read in the application. That reasoning is right and the cost was
 * still wrong, because round trips were never the problem here — the index was.
 *
 * WHY IT WAS SLOW. Every index on this table leads (tenant_id, dataset, …) and
 * none of them carries updated_date. With `dataset` unconstrained, MySQL cannot
 * use any of them for this predicate, so MAX(updated_date) reads the tenant's
 * entire slice — 398,831 rows for Lions — on every page view. Observed on the
 * live database: six concurrent copies of this exact query, each past 800
 * seconds, produced by ordinary navigation. That is the floor the comment
 * describes, resting on a full scan.
 *
 * WHAT THIS CHANGES. (tenant_id, updated_date) makes MAX() a single backward
 * seek to the tenant's last entry, and turns COUNT(*) into a narrow index-only
 * scan instead of a table scan. Both halves of the query are served without
 * reading a row.
 *
 * WHY NOT REWRITE THE FINGERPRINT INSTEAD. Because the fingerprint is right:
 * it has to notice a changed record, and row count plus high-water mark is the
 * cheapest honest way to do that. AcademicIntelligenceService sidesteps the
 * table entirely by fingerprinting the small import-job and projection tables,
 * which is a good pattern — but the profiler serves every organization,
 * including ones whose operational records change without an import job, so it
 * cannot make that assumption. Give the existing query an index rather than
 * weaken what it detects.
 *
 * COST. One more index on a table that already carries seven, so bulk import
 * writes get marginally slower. Measured against a page that was taking
 * thirteen minutes to answer, that is not a close call.
 *
 * IDEMPOTENT AND MYSQL-ONLY, matching the other index migrations here.
 */
return new class extends Migration
{
    private const TABLE = 'hpbrain_operational_records';
    private const INDEX = 'idx_oprec_tenant_updated';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable(self::TABLE)) {
            return;
        }

        if ($this->hasIndex(self::INDEX)) {
            return;
        }

        /*
          BOUNDED LOCK WAIT, BECAUSE THIS DATABASE IS SHARED WITH A LIVE APP.

          ADD INDEX is online in InnoDB — concurrent reads and writes continue —
          but it still needs a brief metadata lock at each end, and it cannot
          take one while an older statement is still reading the table. The
          queries this index exists to eliminate run for many minutes, so an
          unbounded ALTER would sit behind them holding a pending metadata lock,
          and EVERY new query on the table would queue behind that. A migration
          that turns a slow table into an unavailable one is worse than the
          problem it fixes.

          With lock_wait_timeout the ALTER gives up instead. The migration then
          reports that and returns without failing, so the deploy is not blocked
          by transient load — re-run it when the table is quieter.
        */
        DB::statement('SET SESSION lock_wait_timeout = 30');

        try {
            DB::statement('ALTER TABLE '.self::TABLE.' ADD INDEX '.self::INDEX.' (tenant_id, updated_date)');
        } catch (\Illuminate\Database\QueryException $e) {
            // Matched on the driver error code, not on the message text: MySQL
            // writes "Lock wait timeout exceeded" with a capital L, so a
            // str_contains($msg, 'lock') guard silently rethrows the one case it
            // was written to catch. 1205 is lock wait timeout, 1213 a deadlock;
            // both mean "the table was busy", and neither means the DDL is wrong.
            if (! in_array((int) ($e->errorInfo[1] ?? 0), [1205, 1213], true)) {
                throw $e;
            }

            /*
              THROWN, NOT WARNED, AND THAT DISTINCTION COST A ROUND TO LEARN.

              The first version printed a warning and returned normally. Laravel
              then recorded the migration as APPLIED — so the index did not
              exist, `php artisan migrate` reported nothing left to do, and
              re-running could never fix it. A migration that records success
              while doing nothing is the worst of the three outcomes: it is
              indistinguishable from a working one at the only place anybody
              looks.

              Failing leaves the row unwritten, so a later `migrate` retries it.
              Everything before this migration has already committed; only this
              one is outstanding.
            */
            throw new \RuntimeException(
                'Could not acquire a lock on '.self::TABLE.' within 30s to add '.self::INDEX.'. '
                .'The table is busy with long-running reads. Nothing was changed and no other migration '
                .'is affected — re-run `php artisan migrate` when the table is quieter. '
                .'Until this index exists, every intelligence request performs a full scan of this table.',
                0,
                $e,
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable(self::TABLE)) {
            return;
        }

        if ($this->hasIndex(self::INDEX)) {
            DB::statement('ALTER TABLE '.self::TABLE.' DROP INDEX '.self::INDEX);
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
