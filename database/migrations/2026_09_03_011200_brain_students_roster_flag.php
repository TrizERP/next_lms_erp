<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_21_000100_students_roster_flag.php
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
 * Record whether a student is on the ERP's own roster.
 *
 * WHY A THIRD FLAG BESIDE in_academic AND in_fees. Those two say which IMPORTED
 * FILE a student appears in, and until now a student could only exist because
 * one of them named them. That makes the projection a picture of what has been
 * exported rather than of who is enrolled: a school with eight thousand
 * children on its register and no results export publishes zero students, and
 * the Departments, Analytics and Intelligence screens beneath it are all
 * correctly empty for a reason no reader can see.
 *
 * `in_roster` is the answer to a different question — "is this child on the
 * school's register" — and it has to be stored separately for the same reason
 * in_academic and in_fees are separate from each other: a student on the roster
 * with no results is a real and common state, and so is a student in an
 * historical results export who has since left. Collapsing them would lose
 * which of those two a row is.
 *
 * NOTHING IS BACKFILLED. Existing rows get 0, which is the truth about them:
 * they were derived from imported files, and whether the ERP also lists them is
 * a fact the next `students:rebuild` establishes rather than one this migration
 * may assume. An installation whose ERP has no student roster never sets it,
 * and every count it already publishes is unchanged.
 *
 * ADDITIVE AND IDEMPOTENT, in the same shape as the projection-columns
 * migration beside it: ADD COLUMN / ADD INDEX on a Brain-owned table, each
 * guarded by a presence check.
 */
return new class extends Migration
{
    private const TABLE = 'hpbrain_students';

    private const COLUMN = 'in_roster';

    private const INDEX = 'idx_students_tenant_roster';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            DB::statement('ALTER TABLE '.self::TABLE.' ADD COLUMN '.self::COLUMN.' TINYINT(1) NOT NULL DEFAULT 0');
        }

        // SQLite runs the suite and neither needs this index nor exposes
        // information_schema to probe for it.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! $this->hasIndex()) {
            DB::statement('ALTER TABLE '.self::TABLE.' ADD INDEX '.self::INDEX.' (tenant_id, '.self::COLUMN.')');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql' && $this->hasIndex()) {
            DB::statement('ALTER TABLE '.self::TABLE.' DROP INDEX '.self::INDEX);
        }

        if (Schema::hasColumn(self::TABLE, self::COLUMN)) {
            DB::statement('ALTER TABLE '.self::TABLE.' DROP COLUMN '.self::COLUMN);
        }
    }

    private function hasIndex(): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS n
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND INDEX_NAME = ?',
            [self::TABLE, self::INDEX],
        );

        return $row !== null && (int) $row->n > 0;
    }
};
