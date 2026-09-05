<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_18_000300_students_projection_columns.php
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
 * Turn hpbrain_students from an identity list into a read model.
 *
 * WHY THIS TABLE EXISTS AT ALL. Lions' academic export is 388,401 rows, and
 * every one of them is an EXAM RESULT — one subject, one exam, one year, for one
 * student. Treating a row as a person would publish 388,401 people for a school
 * with roughly five thousand. The rows are already stored correctly, as
 * operational records; what was missing is the student, which the export never
 * states directly and which has to be derived by collapsing the rows on
 * enrollment_no.
 *
 * The fee export is the same shape on the other axis: 10,430 receipts against
 * 4,052 GR numbers. `academic.enrollment_no = fees."GR NO."` is the only join
 * the two files support — the names are not reliable keys and are not used.
 *
 * WHY THE COUNTS ARE STORED RATHER THAN JOINED. The People screen shows a page
 * of students with their record counts and paid totals. Computing those per row
 * means a correlated aggregate over a 388k-row table for every student on the
 * page; doing it once per rebuild means the screen is a single indexed range
 * scan. These columns are a projection of hpbrain_operational_records and are
 * rebuilt from it by `students:rebuild` — they are derived, never entered, and
 * never a source of truth. Nothing here invents a student who is not in a file.
 *
 * WHY in_academic / in_fees. The two exports overlap only partially, and the
 * overlap is the single most important fact about this dataset: a student in
 * both files can have academic and fee information related, and a student in
 * only one cannot. Storing the two flags makes "matched", "fee-only" and
 * "result-only" an indexed count instead of a full-outer-join every time the
 * question is asked, and it stops the UI implying a relationship that the data
 * does not support.
 *
 * WHY academic_standard IS SEPARATE FROM standard. The files disagree, and
 * neither is wrong: the fee export says "IX", the academic export says "CBSE-2".
 * They are different vocabularies recorded four years apart. Collapsing them
 * into one column would force a fake reconciliation, so both are kept and
 * labelled by origin.
 *
 * ADDITIVE AND IDEMPOTENT. Only ADD COLUMN / ADD INDEX on a table this codebase
 * introduced, each guarded by a presence check, so re-running is safe and no
 * existing column is altered or dropped.
 */
return new class extends Migration
{
    private const TABLE = 'hpbrain_students';

    /** @var array<string, string> column => DDL type */
    private const COLUMNS = [
        // Which files this student appears in. The matched/fee-only/result-only
        // split is read straight off these two.
        'in_academic'          => 'TINYINT(1) NOT NULL DEFAULT 0',
        'in_fees'              => 'TINYINT(1) NOT NULL DEFAULT 0',

        // The academic export's own words, kept apart from the fee export's.
        'academic_standard'    => 'VARCHAR(191) NULL',

        // Record counts, so a list page never counts rows per student.
        'academic_records'     => 'INT NOT NULL DEFAULT 0',
        'fee_records'          => 'INT NOT NULL DEFAULT 0',

        // Derived academic summary. avg_percentage is SUM(obtain)/SUM(total),
        // not AVG(per-paper percentage) — see StudentProjectionBuilder.
        'avg_percentage'       => 'DECIMAL(6,2) NULL',
        'total_obtained'       => 'DECIMAL(14,2) NULL',
        'total_marks'          => 'DECIMAL(14,2) NULL',
        'subjects_count'       => 'INT NOT NULL DEFAULT 0',
        'first_academic_year'  => 'VARCHAR(8) NULL',
        'last_academic_year'   => 'VARCHAR(8) NULL',

        // Derived fee summary. Only what the receipts actually state: money
        // received. There is no billed or demand column in the source, so no
        // outstanding, overdue or collection-rate figure is stored.
        'total_paid'           => 'DECIMAL(14,2) NULL',
        'first_receipt_date'   => 'DATE NULL',
        'last_receipt_date'    => 'DATE NULL',

        'projected_at'         => 'TIMESTAMP NULL',
    ];

    /** @var array<string, string> index name => column list */
    private const INDEXES = [
        // The People list orders by name and pages through it.
        'idx_students_tenant_name'      => 'tenant_id, student_name',
        // Cohort counts and the cohort filter.
        'idx_students_tenant_cohort'    => 'tenant_id, in_academic, in_fees',
        // Top / bottom performers, and the performance filter.
        'idx_students_tenant_avg'       => 'tenant_id, avg_percentage',
        // Standard-wise rollups, one per vocabulary.
        'idx_students_tenant_acad_std'  => 'tenant_id, academic_standard',
        'idx_students_tenant_paid'      => 'tenant_id, total_paid',
    ];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        foreach (self::COLUMNS as $column => $type) {
            if (! Schema::hasColumn(self::TABLE, $column)) {
                DB::statement('ALTER TABLE '.self::TABLE.' ADD COLUMN '.$column.' '.$type);
            }
        }

        // SQLite runs the test suite and neither needs these indexes nor
        // exposes information_schema to probe for them.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::INDEXES as $name => $columns) {
            if (! $this->hasIndex($name)) {
                DB::statement('ALTER TABLE '.self::TABLE.' ADD INDEX '.$name.' ('.$columns.')');
            }
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
                    DB::statement('ALTER TABLE '.self::TABLE.' DROP INDEX '.$name);
                }
            }
        }

        foreach (array_keys(self::COLUMNS) as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                DB::statement('ALTER TABLE '.self::TABLE.' DROP COLUMN '.$column);
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
