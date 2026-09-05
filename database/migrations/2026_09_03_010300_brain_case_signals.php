<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_12_000200_case_signals.php
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
 * Many signals per case, without disturbing the one that is already there.
 *
 * WHAT IS ACTUALLY WRONG TODAY. hpbrain_cases.signal_id is a single nullable
 * column, so a case can name exactly one signal. A signal may already carry
 * several cases — neither index on that column is unique — so the relationship
 * is many-to-one, and the closed direction is precisely the one an aggregate
 * finding needs: several related signals investigated as one problem.
 *
 * WHY THE COLUMN IS KEPT, AND KEPT AUTHORITATIVE. Seven call sites read
 * hpbrain_cases.signal_id, two of them inside ExplainVerb's hypothesis join —
 * the query the whole EXPLAIN → RECOMMEND path depends on and which is proven
 * by GoldenIntelligenceFlowTest. Replacing the column would rewrite a verb that
 * is currently correct in order to add a capability nothing yet consumes. So
 * this migration is additive only: the column remains the case's PRIMARY signal
 * and every existing reader keeps its present meaning, while the junction
 * carries the additional ones.
 *
 * NO DATA IS MOVED, ONLY COPIED. Nothing writes to or drops hpbrain_cases, and
 * down() drops only the new table. The 19 existing links cannot be lost by
 * running this, by re-running it, or by reversing it.
 *
 * `role` DISTINGUISHES THE ORIGINAL LINK FROM LATER ONES. Without it the only
 * way to answer "which signal was this case opened for?" is to join back to
 * hpbrain_cases.signal_id — the coupling this table exists to relieve. The
 * backfill marks every copied row 'primary'; anything attached afterwards is
 * 'related' unless a caller says otherwise.
 *
 * NOT ENFORCED, DELIBERATELY: "at most one primary per case". MariaDB has no
 * partial unique index, and the generated-column trick that emulates one
 * (UNIQUE over IF(role='primary', case_id, NULL)) buys a constraint at the cost
 * of a column no reader understands. The invariant belongs in the code that
 * attaches signals, where it can also say why it refused.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Both parents must exist: the foreign keys below are real, and a
        // half-built schema must fail here rather than at the first insert.
        if (! Schema::hasTable('hpbrain_cases') || ! Schema::hasTable('hpbrain_signals')) {
            return;
        }

        if (! Schema::hasTable('hpbrain_case_signals')) {
            // Indexes are declared INSIDE the CREATE, not added afterwards with
            // SHOW INDEX guards as the original case_engine migration does.
            // SHOW INDEX is MySQL-only syntax, and inline declaration is both
            // portable and atomic — the table cannot exist un-indexed.
            DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_case_signals (
  tenant_id   VARCHAR(36) NOT NULL,
  case_id     VARCHAR(36) NOT NULL,
  signal_id   VARCHAR(36) NOT NULL,
  role        VARCHAR(32) NOT NULL DEFAULT \'primary\',
  linked_by   VARCHAR(191) NOT NULL,
  linked_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (case_id, signal_id),
  INDEX idx_case_signals_tenant (tenant_id),
  INDEX idx_case_signals_signal (tenant_id, signal_id),
  INDEX idx_case_signals_role (case_id, role),
  CONSTRAINT fk_case_signals_case
    FOREIGN KEY (case_id) REFERENCES hpbrain_cases(id) ON DELETE CASCADE,
  CONSTRAINT fk_case_signals_signal
    FOREIGN KEY (signal_id) REFERENCES hpbrain_signals(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }

        $this->backfill();
    }

    /**
     * Copy every existing case→signal link in as the primary one.
     *
     * PRIMARY KEY (case_id, signal_id) plus insertOrIgnore makes this safe to
     * run repeatedly: a link already carried is skipped, never duplicated and
     * never overwritten — so a re-run cannot clobber a role somebody has since
     * changed by hand.
     *
     * Chunked because insertOrIgnore builds one statement per call and 19 rows
     * today is not 19 rows on a customer database.
     */
    private function backfill(): void
    {
        $rows = DB::table('hpbrain_cases')
            ->whereNotNull('signal_id')
            ->where('signal_id', '!=', '')
            ->select('tenant_id', 'id as case_id', 'signal_id')
            ->get()
            ->map(fn ($r): array => [
                'tenant_id'   => (string) $r->tenant_id,
                'case_id'     => (string) $r->case_id,
                'signal_id'   => (string) $r->signal_id,
                'role'        => 'primary',
                // Stamped so the backfilled rows stay identifiable and this
                // migration's effect is reversible by hand if it ever needs to
                // be undone without dropping links added since.
                'linked_by'   => 'migration:case_signals_backfill',
                'linked_date' => now()->format('Y-m-d H:i:s'),
            ])
            ->all();

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('hpbrain_case_signals')->insertOrIgnore($chunk);
        }

        logger()->info('case_signals_backfilled', ['rows' => count($rows)]);
    }

    /**
     * Drops only the new table. hpbrain_cases.signal_id was never written to,
     * so reversing this cannot lose a link — every one of them is still in the
     * column it came from.
     */
    public function down(): void
    {
        Schema::dropIfExists('hpbrain_case_signals');
    }
};
