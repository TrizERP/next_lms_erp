<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_07_30_000100_measurement_plans.php
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
 * Invariants 3 and 4, made properties of the data rather than of the code.
 *
 * INVARIANT 3 — every action is executable. hpbrain_recommendations had no
 * eso_id, so Module 3 could validate that an `intervene` recommendation named
 * an ESO and then had nowhere to put it: the binding survived exactly as long
 * as the request. Any other writer could still create an actionable
 * recommendation naming nothing.
 *
 * INVARIANT 4 — no ESO runs without a measurement plan defined BEFORE it
 * starts. No such structure existed anywhere. The plan was a free-text string
 * buried in the execution's `input` JSON, written at the same moment as the
 * run, which is not a plan — it is a caption.
 *
 * Follows the conventions of the 2026_01_01_* migrations: raw MySQL DDL,
 * VARCHAR(36) for every id and foreign key (MySQL rejects TEXT in a key, error
 * 1170), and explicit precision on every numeric column (a bare NUMERIC becomes
 * DECIMAL(10,0) and silently rounds a baseline of 0.62 to 1).
 *
 * NULLABLE eso_id on purpose: `watch` and `investigate` recommendations
 * legitimately name no ESO. Making it NOT NULL would force every observation to
 * invent an action.
 */
return new class extends Migration
{
    public function up(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        // ---- Invariant 3: persist the ESO binding ---------------------------
        if (! Schema::hasColumn('hpbrain_recommendations', 'eso_id')) {
            if ($isMysql) {
                DB::unprepared('ALTER TABLE hpbrain_recommendations ADD COLUMN eso_id VARCHAR(36) NULL');
            } else {
                Schema::table('hpbrain_recommendations', function ($table) {
                    $table->string('eso_id', 36)->nullable();
                });
            }
        }

        if ($isMysql && ! $this->indexExists('hpbrain_recommendations', 'idx_recommendations_eso')) {
            DB::unprepared('CREATE INDEX idx_recommendations_eso ON hpbrain_recommendations (tenant_id, eso_id)');
        }

        // A REAL foreign key, declared at table level. The inline `REFERENCES`
        // syntax used throughout the 2026_01_01_* migrations is parsed and then
        // SILENTLY IGNORED by InnoDB — which is why this codebase has spent
        // nine modules checking tenant ownership by hand: the FKs everyone
        // assumed were there never existed. This one does.
        if ($isMysql
            && Schema::hasTable('hpbrain_eso_definitions')
            && ! $this->constraintExists('hpbrain_recommendations', 'fk_recommendations_eso')) {
            DB::unprepared('ALTER TABLE hpbrain_recommendations
                ADD CONSTRAINT fk_recommendations_eso
                FOREIGN KEY (eso_id) REFERENCES hpbrain_eso_definitions(id)');
        }

        // ---- Invariant 4: the measurement plan ------------------------------
        if (Schema::hasTable('hpbrain_measurement_plans')) {
            return;
        }

        if (! $isMysql) {
            Schema::create('hpbrain_measurement_plans', function ($table) {
                $table->string('id', 36)->primary();
                $table->string('tenant_id', 36);
                $table->string('decision_id', 36);
                $table->text('baseline_metric');
                $table->decimal('baseline_value', 18, 4)->nullable();
                $table->decimal('target_value', 18, 4)->nullable();
                $table->string('metric_unit', 50)->nullable();
                $table->integer('measurement_window_days');
                $table->string('owner_id', 36)->nullable();
                $table->text('created_by');
                $table->timestamp('created_date')->nullable();
                $table->index(['tenant_id', 'decision_id'], 'idx_measurement_plans_decision');
            });

            return;
        }

        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_measurement_plans (
  id                      VARCHAR(36) PRIMARY KEY,
  tenant_id               VARCHAR(36) NOT NULL,
  decision_id             VARCHAR(36) NOT NULL,
  baseline_metric         TEXT NOT NULL,
  baseline_value DECIMAL(18,4),
  target_value   DECIMAL(18,4),
  metric_unit             VARCHAR(50),
  measurement_window_days INTEGER NOT NULL DEFAULT 14,
  owner_id                VARCHAR(36),
  created_by              TEXT NOT NULL,
  created_date            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        DB::unprepared('CREATE INDEX idx_measurement_plans_decision
            ON hpbrain_measurement_plans (tenant_id, decision_id)');

        // The ordering check in EsoExecutionController reads created_date to
        // prove the plan pre-dates the run, so it is indexed with the decision.
        DB::unprepared('CREATE INDEX idx_measurement_plans_created
            ON hpbrain_measurement_plans (decision_id, created_date)');

        if (Schema::hasTable('hpbrain_decisions')) {
            DB::unprepared('ALTER TABLE hpbrain_measurement_plans
                ADD CONSTRAINT fk_measurement_plans_decision
                FOREIGN KEY (decision_id) REFERENCES hpbrain_decisions(id)');
        }
    }

    public function down(): void
    {
        $isMysql = DB::getDriverName() === 'mysql';

        Schema::dropIfExists('hpbrain_measurement_plans');

        if ($isMysql && $this->constraintExists('hpbrain_recommendations', 'fk_recommendations_eso')) {
            DB::unprepared('ALTER TABLE hpbrain_recommendations DROP FOREIGN KEY fk_recommendations_eso');
        }

        if ($isMysql && $this->indexExists('hpbrain_recommendations', 'idx_recommendations_eso')) {
            DB::unprepared('DROP INDEX idx_recommendations_eso ON hpbrain_recommendations');
        }

        if (Schema::hasColumn('hpbrain_recommendations', 'eso_id')) {
            Schema::table('hpbrain_recommendations', function ($table) {
                $table->dropColumn('eso_id');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]) !== [];
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        return DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $constraint]
        ) !== [];
    }
};
