<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_07_29_000100_decision_approval.php
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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the human governance gate representable.
 *
 * hpbrain_decisions was created with `status TEXT NOT NULL DEFAULT 'approved'`
 * and no record of who approved anything. A decision was therefore born
 * approved, which makes the approval gate decorative: you cannot authorize a
 * transition that has already happened, and the row cannot answer "who
 * approved this, when, and on what stated ground?" — the question Invariant 2
 * exists to make answerable.
 *
 * Three changes, all additive to data:
 *   1. approved_by / approved_date / approval_note — the approval record.
 *   2. idx_decisions_tenant_status — DecisionController::index() filters on
 *      (tenant_id, status) and the only index was on tenant_id alone.
 *   3. The default becomes 'proposed', so the gate has something to open.
 *
 * NO BACKFILL. Rows created under the old default are left `approved` with a
 * NULL approved_by. We cannot distinguish a deliberate approval from one the
 * default handed out, and inventing an approver — or demoting a genuinely
 * approved decision back to proposed — would put a claim in the audit record
 * that nobody made. An approved row with no approver is the honest statement
 * that this decision predates the gate.
 */
return new class extends Migration
{
    private const TABLE = 'hpbrain_decisions';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            // VARCHAR(36), matching every other actor column in the schema:
            // MySQL rejects TEXT in a key (error 1170) and these are joined on.
            if (! Schema::hasColumn(self::TABLE, 'approved_by')) {
                $table->string('approved_by', 36)->nullable();
            }
            if (! Schema::hasColumn(self::TABLE, 'approved_date')) {
                $table->timestamp('approved_date')->nullable();
            }
            if (! Schema::hasColumn(self::TABLE, 'approval_note')) {
                $table->text('approval_note')->nullable();
            }
        });

        // doctrine/dbal is not installed, so Schema::table()->change() is
        // unavailable and the default has to be restated in raw DDL. Guarded on
        // the driver because this statement is MySQL syntax; the suite runs on
        // SQLite (phpunit.xml), where it would be a syntax error.
        //
        // The column is also widened from TEXT to VARCHAR(255) in the same
        // statement, and that is not cosmetic: MySQL cannot index a TEXT column
        // without a prefix length, so idx_decisions_tenant_status below fails
        // with error 1170 while status stays TEXT. VARCHAR(255) is what
        // hpbrain_signals.status already uses.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE '.self::TABLE." MODIFY status VARCHAR(255) NOT NULL DEFAULT 'proposed'"
            );
        }

        if (! $this->brainHasIndex(self::TABLE, 'idx_decisions_tenant_status')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(['tenant_id', 'status'], 'idx_decisions_tenant_status');
            });
        }
    }

    public function down(): void
    {
        if ($this->brainHasIndex(self::TABLE, 'idx_decisions_tenant_status')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex('idx_decisions_tenant_status');
            });
        }

        // The original column was TEXT DEFAULT 'approved'. The default is
        // restored; the TEXT type is not, because MySQL 8 forbids a DEFAULT on
        // a TEXT column outright — the original DDL only survived because this
        // deployment runs MariaDB. VARCHAR(255) is the reversible form.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE '.self::TABLE." MODIFY status VARCHAR(255) NOT NULL DEFAULT 'approved'"
            );
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn(['approved_by', 'approved_date', 'approval_note']);
        });
    }

    /**
     * Whether an index exists. Schema::hasIndex() only exists from Laravel 11;
     * this project runs Laravel 9, so the catalogue is queried directly.
     */
    private function brainHasIndex(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
              LIMIT 1',
            [$table, $index]
        );

        return $rows !== [];
    }
};
